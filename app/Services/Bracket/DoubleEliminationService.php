<?php

declare(strict_types=1);

namespace App\Services\Bracket;

use App\Enums\TournamentMatchStateEnum;
use App\Models\Tournament;
use App\Models\TournamentMatch;

class DoubleEliminationService extends ModeService
{
    /**
     * @var array<int, int> keyed by match id => winner_id, for matches
     *      already resolved (from a prior run) so dependent matches can be
     *      pre-populated instead of waiting for the next regeneration.
     */
    private array $matchWinner = [];

    /**
     * @var array<int, int|null> keyed by match id => loser_id
     */
    private array $matchLoser = [];

    public function initialize(Tournament $tournament)
    {
        $players = $tournament->players()->checkedIn()->orderBy('seed', 'desc')->get();
        $numPlayers = $players->count();
        $splitParticipant = (bool) ($tournament->split_participant ?? false);

        if ($splitParticipant) {
            // The winners bracket gets exactly half of the *next power-of-two
            // bracket size* (not half the raw headcount) - e.g. for 6 players,
            // the next power of two is 8, so winners gets min(6, 4) = 4 real
            // players and losers gets the remaining 2. This guarantees the
            // winners bracket is always a clean power of two internally (no
            // byes anywhere in it).
            $halfBracketSize = $this->halfOfNextPowerOfTwo($numPlayers);
            $topCount = min($numPlayers, $halfBracketSize);
            $topPlayers = $players->take($topCount)->values();
            $bottomPlayers = $players->slice($topCount)->values();
        } else {
            $topPlayers = $players;
            $bottomPlayers = collect();
        }

        $topPlayerCount = $topPlayers->count();
        $totalWbRounds = $this->calculateTotalRound($topPlayerCount);
        $isSplit = $splitParticipant && $bottomPlayers->isNotEmpty();

        $validMatchIds = [];
        $playOrder = 1;

        // ---- Winners bracket -------------------------------------------------
        // Only the top half plays here when split_participant is enabled;
        // otherwise this is everyone, same as before. Round 1 never shows a
        // bye/TBD match - byes always defer to round 2, exactly as in a
        // normal (non-split) bracket. When split, topPlayerCount is always a
        // clean power of two by construction, so firstRoundMatches() produces
        // zero byes here anyway.
        $wbRounds = [];
        for ($i = 1; $i <= $totalWbRounds; $i++) {
            $matchNumber = $i === 1
                ? (int) $this->firstRoundMatches($topPlayerCount)
                : (int) $this->calculateMatchesCountInRound($totalWbRounds, $i);
            $wbRounds[] = $this->filterPlayersByRound($topPlayers, $matchNumber);
        }

        /** @var array<int, TournamentMatch[]> $wbRoundMatches round => ordered matches */
        $wbRoundMatches = [];
        $wbPreReqQueue = collect();

        foreach ($wbRounds as $roundIndex => $roundPlayers) {
            $round = $roundIndex + 1;
            $wbRoundMatches[$round] = [];
            $player1_id = null;
            $player2_id = null;

            foreach ($roundPlayers as $player) {
                if ($player1_id === null) {
                    $player1_id = $player['id'];
                    continue;
                }

                $player2_id = $player['id'];

                $player1PreReq = null;
                $player2PreReq = null;

                if ($player1_id == 0) {
                    $player1PreReq = $wbPreReqQueue->first();
                    $wbPreReqQueue = $wbPreReqQueue->slice(1)->values();
                }
                if ($player2_id == 0) {
                    $player2PreReq = $wbPreReqQueue->first();
                    $wbPreReqQueue = $wbPreReqQueue->slice(1)->values();
                }

                $resolvedP1 = $this->resolvePlayer($player1_id, $player1PreReq, false);
                $resolvedP2 = $this->resolvePlayer($player2_id, $player2PreReq, false);

                $match = TournamentMatch::updateOrCreate([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'suggested_play_order' => $playOrder,
                ], [
                    'player1_id' => $resolvedP1,
                    'player2_id' => $resolvedP2,
                    'player1_prereq_match_id' => $player1PreReq,
                    'player2_prereq_match_id' => $player2PreReq,
                    'player1_is_prereq_match_loser' => false,
                    'player2_is_prereq_match_loser' => false,
                ]);

                $validMatchIds[] = $match->id;
                $this->rememberOutcome($match);
                $wbPreReqQueue->push($match->id);
                $wbRoundMatches[$round][] = $match;

                $playOrder++;
                $player1_id = null;
                $player2_id = null;
            }
        }

        // ---- Losers bracket ----------------------------------------------------
        /** @var array<int, TournamentMatch[]> $lbRoundMatches lbRound => ordered matches */
        $lbRoundMatches = [];

        // Resolved after either branch below: how the LB champion feeds into
        // the grand final - either as a real match's winner, or (only in a
        // rare edge case) as a raw player who advanced on byes alone without
        // ever playing a tracked match.
        $lbChampionPrereqId = null;
        $lbChampionIsLoser = false;
        $lbChampionRawId = null;

        if ($isSplit) {
            // LB round 1 is seeded from the bottom half, padded to
            // topPlayerCount/2 matches (the same match count as WB round 1).
            // Any bye slot left after placing the bottom half is filled by a
            // WB round 1 loser instead of a true empty bye - once that WB
            // match resolves, its loser drops straight into this exact slot.
            // If there are more WB round 1 losers than bye slots, whatever
            // doesn't fit here merges in on the very next round.
            $lb1MatchCount = intdiv($topPlayerCount, 2);
            $lbRoundCounter = 1;
            $wb1Losers = $wbRoundMatches[1] ?? [];

            [$round1Matches, $leftoverWb1Losers] = $this->buildSplitLbRoundOne(
                $tournament,
                $bottomPlayers,
                $lb1MatchCount,
                $lbRoundCounter,
                $playOrder,
                $wb1Losers,
            );
            $lbRoundMatches[$lbRoundCounter] = $round1Matches;
            $validMatchIds = [...$validMatchIds, ...array_map(fn ($m) => $m->id, $round1Matches)];

            $survivors = array_map(fn ($m) => ['match' => $m->id, 'loser' => false], $round1Matches);
            $carry = array_map(fn ($m) => ['match' => $m->id, 'loser' => true], $leftoverWb1Losers);

            // WB round 1 is already fully accounted for by round 1 above (plus
            // whatever carried over). WB rounds 2..N still need a shrink round
            // (pair survivors together) followed by a merge round (vs that
            // WB round's losers) each.
            for ($k = 2; $k <= $totalWbRounds; $k++) {
                $lbRoundCounter++;
                $population = array_merge($survivors, $carry);
                [$matches, $survivors, $carry] = $this->buildLbRound($tournament, $lbRoundCounter, $playOrder, $population);
                $lbRoundMatches[$lbRoundCounter] = $matches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn ($m) => $m->id, $matches)];

                $lbRoundCounter++;
                $wbLosers = array_map(fn ($m) => ['match' => $m->id, 'loser' => true], $wbRoundMatches[$k] ?? []);
                $population = array_merge($survivors, $carry);
                [$matches, $survivors, $carry] = $this->buildLbRound($tournament, $lbRoundCounter, $playOrder, $population, $wbLosers);
                $lbRoundMatches[$lbRoundCounter] = $matches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn ($m) => $m->id, $matches)];
            }

            // Normally the loop above already leaves exactly one survivor.
            // A skewed split (or WB1 losers that didn't fully fit into round
            // 1's bye slots) can leave more than one - keep shrinking until
            // there's a single LB champion.
            while (count($survivors) + count($carry) > 1) {
                $lbRoundCounter++;
                $population = array_merge($survivors, $carry);
                [$matches, $survivors, $carry] = $this->buildLbRound($tournament, $lbRoundCounter, $playOrder, $population);
                $lbRoundMatches[$lbRoundCounter] = $matches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn ($m) => $m->id, $matches)];
            }

            $totalLbRounds = $lbRoundCounter;

            $finalEntrant = $survivors[0] ?? ($carry[0] ?? null);
            if ($finalEntrant !== null) {
                if (array_key_exists('match', $finalEntrant)) {
                    $lbChampionPrereqId = $finalEntrant['match'];
                    $lbChampionIsLoser = $finalEntrant['loser'] ?? false;
                } else {
                    $lbChampionRawId = $finalEntrant['raw'];
                }
            }
        } else {
            // Non-split: unchanged from the original algorithm. LB round 1 =
            // WB round 1 losers; WB round(k+1) losers merge in every other
            // round after that.
            $totalLbRounds = $totalWbRounds > 1 ? (2 * $totalWbRounds - 2) : 0;

            for ($k = 1; $k < $totalWbRounds; $k++) {
                $oddRound = 2 * $k - 1;
                $evenRound = 2 * $k;

                $oddMatches = [];
                if ($k === 1) {
                    $source = $wbRoundMatches[1] ?? [];
                    foreach (array_chunk($source, 2) as $pair) {
                        if (count($pair) < 2) {
                            continue; // odd leftover, no opponent yet
                        }
                        $oddMatches[] = $this->createLbMatch(
                            $tournament,
                            $oddRound,
                            $playOrder,
                            prereq1: $pair[0]->id,
                            isLoser1: true,
                            prereq2: $pair[1]->id,
                            isLoser2: true,
                        );
                        $playOrder++;
                    }
                } else {
                    $source = $lbRoundMatches[$evenRound - 2] ?? [];
                    foreach (array_chunk($source, 2) as $pair) {
                        if (count($pair) < 2) {
                            continue;
                        }
                        $oddMatches[] = $this->createLbMatch(
                            $tournament,
                            $oddRound,
                            $playOrder,
                            prereq1: $pair[0]->id,
                            isLoser1: false,
                            prereq2: $pair[1]->id,
                            isLoser2: false,
                        );
                        $playOrder++;
                    }
                }
                $lbRoundMatches[$oddRound] = $oddMatches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn ($m) => $m->id, $oddMatches)];

                $lbSurvivors = $lbRoundMatches[$oddRound];
                $wbDropIns = $wbRoundMatches[$k + 1] ?? [];
                $evenMatches = [];
                $pairCount = min(count($lbSurvivors), count($wbDropIns));

                for ($i = 0; $i < $pairCount; $i++) {
                    $evenMatches[] = $this->createLbMatch(
                        $tournament,
                        $evenRound,
                        $playOrder,
                        prereq1: $lbSurvivors[$i]->id,
                        isLoser1: false,
                        prereq2: $wbDropIns[$i]->id,
                        isLoser2: true,
                    );
                    $playOrder++;
                }
                $lbRoundMatches[$evenRound] = $evenMatches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn ($m) => $m->id, $evenMatches)];
            }

            $lbChampionMatch = $lbRoundMatches[$totalLbRounds][0] ?? null;
            if ($lbChampionMatch) {
                $lbChampionPrereqId = $lbChampionMatch->id;
                $lbChampionIsLoser = false;
            }
        }

        // ---- Grand final ---------------------------------------------------
        $wbChampion = $wbRoundMatches[$totalWbRounds][0] ?? null;

        if ($wbChampion && ($lbChampionPrereqId !== null || $lbChampionRawId !== null)) {
            $gf1 = TournamentMatch::updateOrCreate([
                'tournament_id' => $tournament->id,
                'round' => $totalWbRounds + 1,
                'suggested_play_order' => $playOrder,
            ], [
                'player1_id' => $this->resolvePlayer(null, $wbChampion->id, false),
                'player2_id' => $lbChampionPrereqId !== null
                    ? $this->resolvePlayer(null, $lbChampionPrereqId, $lbChampionIsLoser)
                    : $lbChampionRawId,
                'player1_prereq_match_id' => $wbChampion->id,
                'player2_prereq_match_id' => $lbChampionPrereqId,
                'player1_is_prereq_match_loser' => false,
                'player2_is_prereq_match_loser' => $lbChampionIsLoser,
            ]);
            $validMatchIds[] = $gf1->id;
            $this->rememberOutcome($gf1);
            $playOrder++;

            // Bracket reset: only needed if the LB-side player wins game 1.
            $needsReset = $gf1->winner_id !== null && $gf1->winner_id === $gf1->player2_id;

            if ($needsReset) {
                $gf2 = TournamentMatch::updateOrCreate([
                    'tournament_id' => $tournament->id,
                    'round' => $totalWbRounds + 2,
                    'suggested_play_order' => $playOrder,
                ], [
                    'player1_id' => $gf1->winner_id,
                    'player2_id' => $gf1->loser_id,
                    'player1_prereq_match_id' => $gf1->id,
                    'player2_prereq_match_id' => $gf1->id,
                    'player1_is_prereq_match_loser' => false,
                    'player2_is_prereq_match_loser' => true,
                ]);
                $validMatchIds[] = $gf2->id;
                $playOrder++;
            }
        }

        $tournament->matches()
            ->whereNotIn('id', $validMatchIds)
            ->delete();

        $this->updateMatchesState($tournament, $totalWbRounds, $totalLbRounds);

        return $tournament->matches()
            ->orderBy('suggested_play_order', 'asc')
            ->with(['player1', 'player2', 'matchScores'])
            ->get();
    }

    /**
     * Half of the next power of two at or above $numPlayers. This is the
     * winners-bracket size used for split_participant, e.g. for 6 players
     * the next power of two is 8, so this returns 4 - not 3 (half the raw
     * headcount). Forcing the winners bracket to this size guarantees it's
     * always a clean power of two with zero byes.
     */
    private function halfOfNextPowerOfTwo(int $numPlayers): int
    {
        if ($numPlayers < 1) {
            return 0;
        }

        $nextPowerOfTwo = $numPlayers <= 1 ? 1 : (int) pow(2, ceil(log($numPlayers, 2)));

        return max(1, (int) ($nextPowerOfTwo / 2));
    }

    /**
     * Seeds losers-bracket round 1 from the bottom-half of players
     * (split_participant), padded to $matchNumber matches (== winners-bracket
     * round 1's match count). Any bye slot is filled by a winners-bracket
     * round 1 loser (via prereq, resolved once that match completes) rather
     * than a true empty bye - that's what lets "TBD" slots in this round
     * actually get filled in later.
     *
     * @return array{0: TournamentMatch[], 1: TournamentMatch[]} [matches, leftover WB round 1 losers that didn't fit]
     */
    private function buildSplitLbRoundOne(
        Tournament $tournament,
        $bottomPlayers,
        int $matchNumber,
        int $round,
        int &$playOrder,
        array $wb1Losers,
    ): array {
        $paired = $this->filterPlayersByRound($bottomPlayers, $matchNumber);
        $queue = collect($wb1Losers);

        $matches = [];
        $player1_id = null;

        foreach ($paired as $player) {
            if ($player1_id === null) {
                $player1_id = $player['id'];
                continue;
            }

            $player2_id = $player['id'];

            $player1PreReq = null;
            $player2PreReq = null;

            if ($player1_id == 0) {
                $player1PreReq = $queue->first()?->id;
                $queue = $queue->slice(1)->values();
            }
            if ($player2_id == 0) {
                $player2PreReq = $queue->first()?->id;
                $queue = $queue->slice(1)->values();
            }

            $resolvedP1 = $player1PreReq !== null
                ? $this->resolvePlayer(null, $player1PreReq, true)
                : $this->resolvePlayer($player1_id, null, false);
            $resolvedP2 = $player2PreReq !== null
                ? $this->resolvePlayer(null, $player2PreReq, true)
                : $this->resolvePlayer($player2_id, null, false);

            $match = TournamentMatch::updateOrCreate([
                'tournament_id' => $tournament->id,
                'round' => -$round,
                'suggested_play_order' => $playOrder,
            ], [
                'player1_id' => $resolvedP1,
                'player2_id' => $resolvedP2,
                'player1_prereq_match_id' => $player1PreReq,
                'player2_prereq_match_id' => $player2PreReq,
                'player1_is_prereq_match_loser' => $player1PreReq !== null,
                'player2_is_prereq_match_loser' => $player2PreReq !== null,
            ]);

            $this->rememberOutcome($match);
            $matches[] = $match;

            $playOrder++;
            $player1_id = null;
        }

        // Whatever's left in the queue is WB round 1 losers that didn't fit
        // into a bye slot here (only happens when there are more bottom-half
        // players than bye slots) - the caller merges these in next round.
        $leftoverWb1Losers = $queue->values()->all();

        return [$matches, $leftoverWb1Losers];
    }

    /**
     * Generic losers-bracket round builder used from LB round 2 onward in
     * split_participant mode. Each entrant is either:
     *   ['raw' => playerId]                   - a raw seed with no match yet
     *   ['match' => matchId, 'loser' => bool]  - the winner/loser of an
     *                                             earlier match
     *
     * With only $primary given, entrants are paired off among themselves (a
     * "shrink" round). With $secondary also given, $primary[i] is paired
     * against $secondary[i] (a "merge" round, e.g. LB survivors vs WB
     * drop-ins). Whichever side has leftover entrants that couldn't be
     * paired this round is returned as $carry rather than being dropped -
     * the caller folds $carry into the next round's population.
     *
     * @return array{0: TournamentMatch[], 1: array, 2: array} [matches, winnerEntrants, carryEntrants]
     */
    private function buildLbRound(Tournament $tournament, int $round, int &$playOrder, array $primary, array $secondary = []): array
    {
        $matches = [];
        $winners = [];
        $carry = [];

        if (empty($secondary)) {
            $count = count($primary);
            for ($i = 0; $i + 1 < $count; $i += 2) {
                $match = $this->createEntrantMatch($tournament, $round, $playOrder, $primary[$i], $primary[$i + 1]);
                $playOrder++;
                $matches[] = $match;
                $winners[] = ['match' => $match->id, 'loser' => false];
            }
            if ($count % 2 === 1) {
                $carry[] = $primary[$count - 1];
            }
        } else {
            $pairCount = min(count($primary), count($secondary));
            for ($i = 0; $i < $pairCount; $i++) {
                $match = $this->createEntrantMatch($tournament, $round, $playOrder, $primary[$i], $secondary[$i]);
                $playOrder++;
                $matches[] = $match;
                $winners[] = ['match' => $match->id, 'loser' => false];
            }
            if (count($primary) > $pairCount) {
                $carry = array_slice($primary, $pairCount);
            } elseif (count($secondary) > $pairCount) {
                $carry = array_slice($secondary, $pairCount);
            }
        }

        return [$matches, $winners, $carry];
    }

    private function createEntrantMatch(Tournament $tournament, int $round, int $playOrder, array $slot1, array $slot2): TournamentMatch
    {
        $prereq1 = $slot1['match'] ?? null;
        $prereq2 = $slot2['match'] ?? null;
        $isLoser1 = $slot1['loser'] ?? false;
        $isLoser2 = $slot2['loser'] ?? false;

        $match = TournamentMatch::updateOrCreate([
            'tournament_id' => $tournament->id,
            'round' => -$round,
            'suggested_play_order' => $playOrder,
        ], [
            'player1_id' => $this->resolveEntrantPlayer($slot1),
            'player2_id' => $this->resolveEntrantPlayer($slot2),
            'player1_prereq_match_id' => $prereq1,
            'player2_prereq_match_id' => $prereq2,
            'player1_is_prereq_match_loser' => $isLoser1,
            'player2_is_prereq_match_loser' => $isLoser2,
        ]);

        $this->rememberOutcome($match);

        return $match;
    }

    /**
     * @param array{raw?: int, match?: int, loser?: bool} $entrant
     */
    private function resolveEntrantPlayer(array $entrant): ?int
    {
        if (array_key_exists('raw', $entrant)) {
            return $this->resolvePlayer($entrant['raw'], null, false);
        }

        return $this->resolvePlayer(null, $entrant['match'], $entrant['loser'] ?? false);
    }

    private function createLbMatch(
        Tournament $tournament,
        int $round,
        int $playOrder,
        int $prereq1,
        bool $isLoser1,
        int $prereq2,
        bool $isLoser2,
    ): TournamentMatch {
        $match = TournamentMatch::updateOrCreate([
            'tournament_id' => $tournament->id,
            'round' => -$round, // losers bracket rounds are negative: -1, -2, -3...
            'suggested_play_order' => $playOrder,
        ], [
            'player1_id' => $this->resolvePlayer(null, $prereq1, $isLoser1),
            'player2_id' => $this->resolvePlayer(null, $prereq2, $isLoser2),
            'player1_prereq_match_id' => $prereq1,
            'player2_prereq_match_id' => $prereq2,
            'player1_is_prereq_match_loser' => $isLoser1,
            'player2_is_prereq_match_loser' => $isLoser2,
        ]);

        $this->rememberOutcome($match);

        return $match;
    }

    /**
     * Resolve an actual player id for a slot, given either a raw seeded id
     * (0 == bye, used for WB round 1) or a prereq match id to pull a
     * winner/loser from once that match has been decided.
     */
    private function resolvePlayer(?int $rawId, ?int $prereqMatchId, bool $isLoserPrereq): ?int
    {
        if ($prereqMatchId === null) {
            return ($rawId === null || $rawId === 0) ? null : $rawId;
        }

        if ($isLoserPrereq) {
            return $this->matchLoser[$prereqMatchId] ?? null;
        }

        return $this->matchWinner[$prereqMatchId] ?? null;
    }

    private function rememberOutcome(TournamentMatch $match): void
    {
        if ($match->winner_id === null) {
            return;
        }

        $this->matchWinner[$match->id] = $match->winner_id;
        $this->matchLoser[$match->id] = $match->loser_id
            ?? ($match->player1_id === $match->winner_id ? $match->player2_id : $match->player1_id);
    }

    private function updateMatchesState(Tournament $tournament, int $totalWbRounds, int $totalLbRounds): void
    {
        $tournament->matches()
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->update(['state' => TournamentMatchStateEnum::OPEN]);

        $tournament->matches()
            ->whereNotNull('winner_id')
            ->update(['state' => TournamentMatchStateEnum::COMPLETE]);

        $grandFinals = $tournament->matches()
            ->where('round', '>', $totalWbRounds)
            ->orderByDesc('round')
            ->get();

        $decisiveFinal = $grandFinals->first(fn ($m) => $m->winner_id !== null);
        $allOthersDone = !$tournament->matches()
            ->where('round', '<=', $totalWbRounds)
            ->whereNull('winner_id')
            ->exists();

        if ($allOthersDone && $decisiveFinal) {
            $this->assignFinalRanks($tournament, $totalWbRounds, $totalLbRounds);
        } else {
            $tournament->players()->update(['final_rank' => null]);
        }
    }

    private function assignFinalRanks(Tournament $tournament, int $totalWbRounds, int $totalLbRounds): void
    {
        $grandFinal = $tournament->matches()
            ->where('round', '>', $totalWbRounds)
            ->orderByDesc('round')
            ->whereNotNull('winner_id')
            ->first();

        if (!$grandFinal) {
            return;
        }

        $tournament->players()->find($grandFinal->winner_id)?->update(['final_rank' => 1]);
        $tournament->players()->find($grandFinal->loser_id)?->update(['final_rank' => 2]);

        $rank = 3;
        for ($round = $totalLbRounds; $round >= 1; $round--) {
            $losers = $tournament->matches()
                ->where('round', -$round)
                ->whereNotNull('loser_id')
                ->pluck('loser_id');

            if ($losers->isEmpty()) {
                continue;
            }

            foreach ($losers as $loserId) {
                $tournament->players()->find($loserId)?->update(['final_rank' => $rank]);
            }

            $rank += $losers->count();
        }
    }

    private function filterPlayersByRound(&$players, int $matchNumber)
    {
        $num = $matchNumber * 2;
        $data = $players->take($num);
        $players = collect($players->slice($num))->values();

        $missing = $num - $data->count();

        if ($missing > 0) {
            $data = $data->values();

            for ($i = 0; $i < $missing; $i++) {
                if ($i % 2 === 0) {
                    $position = $i;
                } else {
                    $position = $data->count() - $i + 1;
                    if ($position < 0) {
                        $position = $data->count();
                    }
                }

                $data->splice($position, 0, [['id' => 0]]);
            }
        }

        return $data;
    }

    private function firstRoundMatches(int $numPlayers)
    {
        if ($numPlayers < 2) {
            return 0;
        }

        $nextPowerOfTwo = pow(2, ceil(log($numPlayers, 2)));
        $byes = $nextPowerOfTwo - $numPlayers;

        return ($numPlayers - $byes) / 2;
    }
}