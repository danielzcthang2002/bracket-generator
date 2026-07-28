<?php

declare(strict_types=1);

namespace App\Services\Bracket;

use App\Enums\TournamentMatchStateEnum;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;

/**
 * Round Robin bracket generation, modeled on Challonge's round-robin behavior:
 *  - Every checked-in player plays every other player exactly once
 *    (this generates a *single* round robin; call initialize() twice if you
 *    want double round robin, or see the $rounds multiplier note below).
 *  - Scheduling uses the standard "circle method": one player is fixed,
 *    the rest rotate one position each round, and in round r the players
 *    at mirrored positions are paired. This spreads each player's matches
 *    evenly across rounds instead of front-loading them.
 *  - Odd player counts get a rotating "bye": one player sits out each round.
 *    No TournamentMatch row is created for a bye (there's no opponent), but
 *    the bye is counted so the player can still receive points_per_bye
 *    credit in the standings.
 *  - Standings (and therefore final_rank) are NOT determined by who is
 *    still "alive" (there's no elimination) — they're computed from
 *    points_per_match_win / points_per_match_tie / points_per_set_win /
 *    points_per_set_tie / points_per_bye, with head-to-head record among
 *    tied players as the tiebreaker, falling back to seed.
 */
class RoundRobinService extends ModeService
{
    public function initialize(Tournament $tournament)
    {
        $players = $tournament->players()->checkedIn()->orderBy('seed', 'desc')->get();
        $numPlayers = $players->count();

        if ($numPlayers < 2) {
            $tournament->matches()->delete();
            return collect();
        }

        $playerIds = $players->pluck('id')->values()->all();

        $hasBye = false;
        if ($numPlayers % 2 !== 0) {
            // 0 is our "bye" sentinel, same convention SingleEliminationService uses.
            $playerIds[] = 0;
            $numPlayers++;
            $hasBye = true;
        }

        $numRounds = $numPlayers - 1;
        $half = $numPlayers / 2;

        $validMatchIds = [];
        $suggestedPlayOrder = 1;
        $order = $playerIds;

        for ($round = 1; $round <= $numRounds; $round++) {
            for ($i = 0; $i < $half; $i++) {
                $player1_id = $order[$i];
                $player2_id = $order[$numPlayers - 1 - $i];

                // Skip bye pairings entirely — no match row, no opponent.
                if ($player1_id === 0 || $player2_id === 0) {
                    continue;
                }

                $match = TournamentMatch::updateOrCreate([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'suggested_play_order' => $suggestedPlayOrder,
                ], [
                    'player1_id' => $player1_id,
                    'player2_id' => $player2_id,
                    'player1_prereq_match_id' => null,
                    'player2_prereq_match_id' => null,
                ]);

                $validMatchIds[] = $match->id;
                $suggestedPlayOrder++;
            }

            $order = $this->rotate($order);
        }

        $tournament->matches()
            ->whereNotIn('id', $validMatchIds)
            ->delete();

        $this->updateMatchesState($tournament, $hasBye);

        return $tournament->matches()
            ->orderBy('suggested_play_order', 'asc')
            ->with(['player1', 'player2', 'matchScores'])
            ->get();
    }

    /**
     * Standard round-robin "circle method" rotation: keep the first seat
     * fixed, move the last seat to position 1, and shift everyone else
     * down one spot.
     *
     * [0,1,2,3] -> [0,3,1,2] -> [0,2,3,1] -> ... cycles through n-1 rounds
     * and produces every unique pairing exactly once.
     */
    private function rotate(array $order): array
    {
        $n = count($order);
        $fixed = $order[0];
        $last = $order[$n - 1];
        $middle = array_slice($order, 1, $n - 2);

        return array_merge([$fixed, $last], $middle);
    }

    private function updateMatchesState(Tournament $tournament, bool $hasBye): void
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

        $allComplete = !$tournament->matches()
            ->whereNull('winner_id')
            ->where('is_tie', false)
            ->exists();

        if ($allComplete) {
            $this->assignFinalRanks($tournament, $hasBye);
        } else {
            $tournament->players()->update(['final_rank' => null]);
        }
    }

    private function assignFinalRanks(Tournament $tournament, bool $hasBye): void
    {
        $standings = $this->computeStandings($tournament, $hasBye);

        // Group players into tiers of equal points, since round-robin
        // (unlike single elim) can produce genuine ties.
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
     * Compute wins/losses/ties/points/set record for every checked-in player.
     * Byes contribute points_per_bye but never touch win/loss/set counts.
     */
    private function computeStandings(Tournament $tournament, bool $hasBye): Collection
    {
        $players = $tournament->players()->checkedIn()->get();

        // Plain array, not a Collection: Collection's ArrayAccess doesn't
        // return offsetGet() by reference, so `$stats[$id]['wins']++` below
        // would throw "Indirect modification of overloaded element ... has
        // no effect" if $stats were a Collection. A plain array supports
        // nested mutation fine; we wrap it in a Collection only at return.
        $stats = [];
        foreach ($players as $p) {
            $stats[$p->id] = [
                'player_id' => $p->id,
                'wins' => 0,
                'losses' => 0,
                'ties' => 0,
                'set_wins' => 0,
                'set_losses' => 0,
                'points' => 0.0,
            ];
        }

        $matches = $tournament->matches()
            ->where(function ($q) {
                $q->whereNotNull('winner_id')->orWhere('is_tie', true);
            })
            ->with('matchScores')
            ->get();

        $winPoints = (float) $tournament->points_per_match_win;
        $tiePoints = (float) $tournament->points_per_match_tie;
        $setWinPoints = (float) $tournament->points_per_set_win;
        $setTiePoints = (float) $tournament->points_per_set_tie;

        foreach ($matches as $match) {
            if (!$match->player1_id || !$match->player2_id) {
                continue;
            }
            if (!isset($stats[$match->player1_id]) || !isset($stats[$match->player2_id])) {
                continue;
            }

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

            // Tally set-level results if scores were recorded for this match.
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

        if ($hasBye) {
            $byePoints = (float) $tournament->points_per_bye;
            $totalRounds = $players->count(); // n players -> n rounds when a bye seat exists
            foreach ($players as $player) {
                $roundsPlayed = $tournament->matches()
                    ->where(function ($q) use ($player) {
                        $q->where('player1_id', $player->id)->orWhere('player2_id', $player->id);
                    })
                    ->count();
                $byes = max(0, $totalRounds - $roundsPlayed);
                $stats[$player->id]['points'] += $byes * $byePoints;
            }
        }

        return collect(array_values($stats));
    }

    /**
     * Order a tied-on-points group by head-to-head wins among just that
     * group, then set-win percentage, then seed as a final deterministic
     * fallback (lower seed number wins the tiebreak, matching how seeding
     * is already used elsewhere in this codebase).
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
            $setTotal = $p['set_wins'] + $p['set_losses'];
            $setPct = $setTotal > 0 ? $p['set_wins'] / $setTotal : 0;

            return [
                -($h2hWins[$p['player_id']] ?? 0),
                -$setPct,
                $seeds[$p['player_id']] ?? PHP_INT_MAX,
            ];
        })->values();

        return $sorted->pluck('player_id')->all();
    }
}