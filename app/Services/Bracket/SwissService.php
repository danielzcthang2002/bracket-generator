<?php

declare(strict_types=1);

namespace App\Services\Bracket;

use App\Enums\TournamentMatchStateEnum;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\Bracket\Contracts\HasMode;
use Illuminate\Support\Collection;

/**
 * Swiss-system bracket generation.
 *
 * Unlike single/double elimination and round robin, a Swiss bracket can't be
 * generated all at once: round N+1's pairings depend on the *results* of
 * round N (players are paired against others with a similar record). So,
 * unlike the other ModeServices, initialize() is designed to be called
 * repeatedly over the life of the event (e.g. once after every round is
 * fully reported) and will only ever:
 *   - create round 1 the first time it's called (seeded "fold" pairing), and
 *   - append the next round once the round before it is fully decided.
 * It never rewrites a round that already has matches in it, since those
 * matches may already have been played - there's no analog here to the
 * other services' `whereNotIn($validMatchIds)->delete()` cleanup.
 *
 * Pairing:
 *   - Round 1: seed-ordered "fold" pairing (1 vs half+1, 2 vs half+2, ...),
 *     same idea as single elimination's first round.
 *   - Round 2+: players are sorted by current standing (points, then
 *     Buchholz, then seed) and greedily paired top-down with the nearest
 *     unpaired player they haven't already played. This is a simplified
 *     heuristic, not a full Dutch-system pairing engine (no look-ahead or
 *     backtracking to guarantee zero rematches across the whole round) -
 *     it's the same practical trade-off most lightweight Swiss
 *     implementations make, and rematches are only ever forced as a last
 *     resort when a player has already played everyone left to pair.
 *   - Odd player counts: one match-less "bye" row is created per round
 *     (player2_id null, winner_id = player1_id, auto-COMPLETE) so it's
 *     trivial to query who has already had a bye. The bye goes to the
 *     lowest-standing player who hasn't had one yet.
 *
 * Standings/points mirror RoundRobinService: points_per_match_win /
 * points_per_match_tie / points_per_set_win / points_per_set_tie /
 * points_per_bye. Tiebreaking uses Buchholz (sum of opponents' points -
 * the standard Swiss tiebreaker) first, since two players tied on points in
 * a Swiss event often never played each other, then head-to-head, then seed.
 *
 * Total rounds defaults to ceil(log2(n)) - the conventional number of Swiss
 * rounds needed to separate a field of n players - but can be overridden
 * per tournament via a `swiss_rounds` column/attribute if one exists.
 */
class SwissService extends ModeService implements HasMode
{
    public function initialize(Tournament $tournament)
    {
        $players = $tournament->players()->checkedIn()->orderBy('id', 'asc')->get();
        $numPlayers = $players->count();

        if ($numPlayers < 2) {
            $tournament->matches()->delete();
            return collect();
        }

        $totalRounds = (int) ($tournament->swiss_rounds ?? $this->defaultSwissRoundCount($numPlayers));
        $lastRound = (int) $tournament->matches()->max('round');

        if ($lastRound === 0) {
            // Nothing generated yet: seed round 1 straight from seeding.
            $this->generateRound($tournament, 1, $players);
        } elseif ($lastRound < $totalRounds && $this->roundIsComplete($tournament, $lastRound)) {
            // Previous round is fully decided and there's at least one more
            // round to go - pair the next round off current standings.
            $standings = $this->computeStandings($tournament, $players);
            $ordered = $this->orderByStanding($players, $standings);
            $this->generateRound($tournament, $lastRound + 1, $ordered);
        }
        // else: the most recent round is still in progress, or every round
        // has already been generated - nothing to do this call.

        $this->updateMatchesState($tournament, $totalRounds);

        return $tournament->matches()
            ->orderBy('suggested_play_order', 'asc')
            ->with(['player1', 'player2', 'matchScores'])
            ->get();
    }

    /**
     * Conventional Swiss round count: enough rounds that, in principle, a
     * single player could separate themselves from the rest of an n-player
     * field purely on match wins.
     */
    private function defaultSwissRoundCount(int $numPlayers): int
    {
        return max(1, (int) ceil(log($numPlayers, 2)));
    }

    /**
     * Create every match (and the bye row, if any) for a single round.
     * $orderedPlayers must already be in the order pairing should walk
     * top-down: seed order for round 1, standings order for round 2+.
     */
    private function generateRound(Tournament $tournament, int $round, Collection $orderedPlayers): void
    {
        $playOrder = ((int) $tournament->matches()->max('suggested_play_order')) + 1;

        if ($round === 1) {
            [$pairs, $byePlayerId] = $this->pairFold($orderedPlayers);
        } else {
            $playedPairs = $this->playedPairSet($tournament);
            $priorByes = $this->priorByePlayerIds($tournament);
            [$pairs, $byePlayerId] = $this->pairSwiss($orderedPlayers, $playedPairs, $priorByes);
        }

        foreach ($pairs as [$player1Id, $player2Id]) {
            TournamentMatch::updateOrCreate([
                'tournament_id' => $tournament->id,
                'round' => $round,
                'suggested_play_order' => $playOrder,
            ], [
                'player1_id' => $player1Id,
                'player2_id' => $player2Id,
                'player1_prereq_match_id' => null,
                'player2_prereq_match_id' => null,
            ]);

            $playOrder++;
        }

        if ($byePlayerId !== null) {
            TournamentMatch::updateOrCreate([
                'tournament_id' => $tournament->id,
                'round' => $round,
                'player1_id' => $byePlayerId,
                'player2_id' => null,
            ], [
                'suggested_play_order' => $playOrder,
                'winner_id' => $byePlayerId,
                'state' => TournamentMatchStateEnum::COMPLETE,
            ]);
        }
    }

    /**
     * Round 1: standard "fold" seeding - split the seed-ordered field in
     * half and pair rank i (top half) against rank i (bottom half), e.g.
     * for 8 players: 1v5, 2v6, 3v7, 4v8. If the field is odd, the single
     * lowest remaining seed gets the round's bye.
     *
     * @return array{0: array<int, array{0:int,1:int}>, 1: int|null} [pairs, byePlayerId]
     */
    private function pairFold(Collection $ordered): array
    {
        $ids = $ordered->pluck('id')->values();
        $byePlayerId = null;

        if ($ids->count() % 2 === 1) {
            $byePlayerId = $ids->pop();
        }

        $half = intdiv($ids->count(), 2);
        $top = $ids->slice(0, $half)->values();
        $bottom = $ids->slice($half)->values();

        $pairs = [];
        for ($i = 0; $i < $half; $i++) {
            $pairs[] = [$top[$i], $bottom[$i]];
        }

        return [$pairs, $byePlayerId];
    }

    /**
     * Round 2+: players arrive already sorted by standing (best first). For
     * each unpaired player in that order, pair them with the nearest
     * unpaired player below them that they haven't already played. If a
     * player has already played everyone left to pair (only realistic with
     * a very small field run for many rounds), they're forced into a
     * rematch with the nearest remaining player rather than left out.
     *
     * @param array<string, true> $playedPairs set of "loId-hiId" keys already played
     * @param array<int, true> $priorByes player ids who have already had a bye
     * @return array{0: array<int, array{0:int,1:int}>, 1: int|null} [pairs, byePlayerId]
     */
    private function pairSwiss(Collection $ordered, array $playedPairs, array $priorByes): array
    {
        $pool = $ordered->pluck('id')->values()->all();
        $byePlayerId = null;

        if (count($pool) % 2 === 1) {
            // Give the bye to the lowest-standing player who hasn't had one
            // yet; if everyone already has, fall back to the lowest standing.
            $byeIndex = null;
            for ($i = count($pool) - 1; $i >= 0; $i--) {
                if (!isset($priorByes[$pool[$i]])) {
                    $byeIndex = $i;
                    break;
                }
            }
            $byeIndex ??= count($pool) - 1;

            $byePlayerId = $pool[$byeIndex];
            unset($pool[$byeIndex]);
            $pool = array_values($pool);
        }

        $unpaired = $pool;
        $pairs = [];

        while (count($unpaired) > 0) {
            $player = array_shift($unpaired);
            $opponentIndex = null;

            foreach ($unpaired as $idx => $candidate) {
                if (!isset($playedPairs[$this->pairKey($player, $candidate)])) {
                    $opponentIndex = $idx;
                    break;
                }
            }

            // No un-played opponent left - forced rematch with the nearest
            // remaining player rather than dropping anyone.
            $opponentIndex ??= 0;

            $opponent = $unpaired[$opponentIndex];
            unset($unpaired[$opponentIndex]);
            $unpaired = array_values($unpaired);

            $pairs[] = [$player, $opponent];
        }

        return [$pairs, $byePlayerId];
    }

    private function pairKey(int $a, int $b): string
    {
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }

    /**
     * @return array<string, true> every "loId-hiId" pairing already played
     */
    private function playedPairSet(Tournament $tournament): array
    {
        $set = [];

        $tournament->matches()
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->get(['player1_id', 'player2_id'])
            ->each(function ($match) use (&$set) {
                $set[$this->pairKey($match->player1_id, $match->player2_id)] = true;
            });

        return $set;
    }

    /**
     * @return array<int, true> player ids who have already had a bye row
     */
    private function priorByePlayerIds(Tournament $tournament): array
    {
        return $tournament->matches()
            ->whereNotNull('player1_id')
            ->whereNull('player2_id')
            ->pluck('player1_id')
            ->flip()
            ->map(fn() => true)
            ->all();
    }

    private function roundIsComplete(Tournament $tournament, int $round): bool
    {
        return !$tournament->matches()
            ->where('round', $round)
            ->where('state', '!=', TournamentMatchStateEnum::COMPLETE)
            ->exists();
    }

    private function updateMatchesState(Tournament $tournament, int $totalRounds): void
    {
        $tournament->matches()
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->whereNull('winner_id')
            ->where('is_tie', false)
            ->update(['state' => TournamentMatchStateEnum::OPEN]);

        $tournament->matches()
            ->where(function ($q) {
                $q->whereNotNull('winner_id')->orWhere('is_tie', true);
            })
            ->update(['state' => TournamentMatchStateEnum::COMPLETE]);

        $lastRound = (int) $tournament->matches()->max('round');
        $allDecided = !$tournament->matches()
            ->whereNull('winner_id')
            ->where('is_tie', false)
            ->exists();

        if ($lastRound >= $totalRounds && $allDecided) {
            $this->assignFinalRanks($tournament);
        } else {
            $tournament->players()->update(['final_rank' => null]);
        }
    }

    private function assignFinalRanks(Tournament $tournament): void
    {
        $players = $tournament->players()->checkedIn()->get();
        $standings = $this->computeStandings($tournament, $players);

        // Group players into tiers of equal points - Swiss, like round
        // robin, can produce genuine ties that need breaking.
        $tiers = $standings->groupBy('points')->sortKeysDesc();

        $rank = 1;
        foreach ($tiers as $tierPlayers) {
            $orderedIds = $this->breakTies($tournament, $tierPlayers);

            foreach ($orderedIds as $playerId) {
                $tournament->players()->find($playerId)?->update(['final_rank' => $rank]);
                $rank++;
            }
        }
    }

    /**
     * Order a tied-on-points group by Buchholz (the standard Swiss
     * tiebreaker - rewards having played a tougher schedule), then
     * head-to-head wins within just that group, then seed. Buchholz takes
     * priority over head-to-head here (unlike RoundRobinService's set-based
     * tiebreak) because players tied on points in a Swiss event frequently
     * never played each other at all.
     */
    private function breakTies(Tournament $tournament, Collection $tierPlayers): array
    {
        if ($tierPlayers->count() <= 1) {
            return $tierPlayers->pluck('player_id')->all();
        }

        $ids = $tierPlayers->pluck('player_id')->all();

        $h2hWins = array_fill_keys($ids, 0);
        $tournament->matches()
            ->whereIn('player1_id', $ids)
            ->whereIn('player2_id', $ids)
            ->whereNotNull('winner_id')
            ->get()
            ->each(function ($match) use (&$h2hWins) {
                $h2hWins[$match->winner_id] = ($h2hWins[$match->winner_id] ?? 0) + 1;
            });

        $seeds = $tournament->players()
            ->whereIn('id', $ids)
            ->pluck('seed', 'id');

        $sorted = $tierPlayers->sortBy(function ($p) use ($h2hWins, $seeds) {
            return [
                - ($p['buchholz'] ?? 0.0),
                - ($h2hWins[$p['player_id']] ?? 0),
                $seeds[$p['player_id']] ?? PHP_INT_MAX,
            ];
        })->values();

        return $sorted->pluck('player_id')->all();
    }

    /**
     * Order a players collection by current standing: points desc, then
     * Buchholz desc, then seed asc. Used to decide pairing order for
     * round 2+ (round 1 uses seed order directly, before any results
     * exist).
     */
    private function orderByStanding(Collection $players, Collection $standings): Collection
    {
        $byId = $standings->keyBy('player_id');

        return $players->sortBy(function ($player) use ($byId) {
            $row = $byId->get($player->id);

            return [
                - ($row['points'] ?? 0.0),
                - ($row['buchholz'] ?? 0.0),
                $player->seed ?? PHP_INT_MAX,
            ];
        })->values();
    }

    /**
     * Points/tiebreaker table for every checked-in player, from matches
     * decided so far. Bye rows (player2_id null) grant points_per_bye and
     * are excluded from win/loss/set counts and from the Buchholz sum
     * (a bye isn't an opponent).
     */
    private function computeStandings(Tournament $tournament, Collection $players): Collection
    {
        $stats = [];
        foreach ($players as $p) {
            $stats[$p->id] = [
                'player_id' => $p->id,
                'wins' => 0,
                'losses' => 0,
                'ties' => 0,
                'byes' => 0,
                'set_wins' => 0,
                'set_losses' => 0,
                'points' => 0.0,
                'opponents' => [],
            ];
        }

        $winPoints = (float) $tournament->points_per_match_win;
        $tiePoints = (float) $tournament->points_per_match_tie;
        $setWinPoints = (float) $tournament->points_per_set_win;
        $setTiePoints = (float) $tournament->points_per_set_tie;
        $byePoints = (float) $tournament->points_per_bye;

        $matches = $tournament->matches()
            ->whereNotNull('player1_id')
            ->where(function ($q) {
                $q->whereNotNull('winner_id')->orWhere('is_tie', true);
            })
            ->with('matchScores')
            ->get();

        foreach ($matches as $match) {
            if (!isset($stats[$match->player1_id])) {
                continue;
            }

            // Bye row: credit points_per_bye and move on - no opponent,
            // no set data, doesn't touch Buchholz.
            if ($match->player2_id === null) {
                $stats[$match->player1_id]['byes']++;
                $stats[$match->player1_id]['points'] += $byePoints;
                continue;
            }

            if (!isset($stats[$match->player2_id])) {
                continue;
            }

            $stats[$match->player1_id]['opponents'][] = $match->player2_id;
            $stats[$match->player2_id]['opponents'][] = $match->player1_id;

            if ($match->is_tie) {
                $stats[$match->player1_id]['ties']++;
                $stats[$match->player2_id]['ties']++;
                $stats[$match->player1_id]['points'] += $tiePoints;
                $stats[$match->player2_id]['points'] += $tiePoints;
            } else {
                $loserId = $match->player1_id === $match->winner_id
                    ? $match->player2_id
                    : $match->player1_id;

                $stats[$match->winner_id]['wins']++;
                $stats[$loserId]['losses']++;
                $stats[$match->winner_id]['points'] += $winPoints;
            }

            foreach ($match->matchScores->groupBy('set') as $setScores) {
                if ($setScores->count() !== 2) {
                    continue; // incomplete/malformed set data, skip
                }

                $a = $setScores[0];
                $b = $setScores[1];

                if (!isset($stats[$a->player_id], $stats[$b->player_id])) {
                    continue;
                }

                if ($a->score == $b->score) {
                    $stats[$a->player_id]['points'] += $setTiePoints;
                    $stats[$b->player_id]['points'] += $setTiePoints;
                } else {
                    [$winnerRow, $loserRow] = $a->score > $b->score ? [$a, $b] : [$b, $a];
                    $stats[$winnerRow->player_id]['set_wins']++;
                    $stats[$loserRow->player_id]['set_losses']++;
                    $stats[$winnerRow->player_id]['points'] += $setWinPoints;
                }
            }
        }

        // Buchholz: sum of each player's opponents' points. Computed after
        // the main loop so every opponent's own points total is final.
        foreach ($stats as &$row) {
            $row['buchholz'] = array_sum(array_map(
                fn($opponentId) => $stats[$opponentId]['points'] ?? 0.0,
                $row['opponents']
            ));
        }
        unset($row);

        return collect(array_values($stats));
    }
}
