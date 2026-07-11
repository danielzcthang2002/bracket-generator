<?php

declare(strict_types=1);

namespace App\Services\Bracket;

use App\Enums\TournamentMatchStateEnum;
use App\Models\Tournament;
use App\Models\TournamentMatch;

class DoubleEliminationService extends ModeService
{
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
            // Bottom half starts already in the losers bracket instead of the
            // winners bracket. Top half keeps the extra player when N is odd.
            $topCount = (int) ceil($numPlayers / 2);
            $topPlayers = $players->take($topCount)->values();
            $bottomPlayers = $players->slice($topCount)->values();
        } else {
            $topPlayers = $players;
            $bottomPlayers = collect();
        }

        $topPlayerCount = $topPlayers->count();
        $totalWbRounds = $this->calculateTotalRound($topPlayerCount);

        $validMatchIds = [];
        $playOrder = 1;

        // ---- Winners bracket -------------------------------------------------
        // Only the top half plays here when split_participant is enabled;
        // otherwise this is everyone, same as before.
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
        $totalLbRounds = $totalWbRounds > 1 ? (2 * $totalWbRounds - 2) : 0;

        for ($k = 1; $k < $totalWbRounds; $k++) {
            $oddRound = 2 * $k - 1;
            $evenRound = 2 * $k;

            // Odd round: either fresh WB1 losers (k===1) or LB survivors pairing off (k>1)
            // When split_participant is on, LB round 1 (k===1) is instead seeded
            // directly from the bottom-half players rather than WB1 losers.
            $oddMatches = [];
            if ($k === 1 && $splitParticipant && $bottomPlayers->isNotEmpty()) {
                $oddMatches = $this->buildSplitLbRoundOne($tournament, $bottomPlayers, $oddRound, $playOrder);
            } elseif ($k === 1) {
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

            // Even round: LB survivors vs freshly-dropped WB(k+1) losers
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

        // ---- Grand final ---------------------------------------------------
        $wbChampion = $wbRoundMatches[$totalWbRounds][0] ?? null;
        $lbChampion = $lbRoundMatches[$totalLbRounds][0] ?? null;

        if ($wbChampion && $lbChampion) {
            $gf1 = TournamentMatch::updateOrCreate([
                'tournament_id' => $tournament->id,
                'round' => $totalWbRounds + 1,
                'suggested_play_order' => $playOrder,
            ], [
                'player1_id' => $this->resolvePlayer(null, $wbChampion->id, false),
                'player2_id' => $this->resolvePlayer(null, $lbChampion->id, false),
                'player1_prereq_match_id' => $wbChampion->id,
                'player2_prereq_match_id' => $lbChampion->id,
                'player1_is_prereq_match_loser' => false,
                'player2_is_prereq_match_loser' => false,
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
     * Seeds losers-bracket round 1 directly from the bottom-half of players
     * (split_participant), instead of the round being fed by winners-bracket
     * round 1 losers. Mirrors the winners-bracket round 1 pairing loop, but
     * writes straight into the losers bracket with no prereq match.
     *
     * @return TournamentMatch[]
     */
    private function buildSplitLbRoundOne(Tournament $tournament, $bottomPlayers, int $round, int &$playOrder): array
    {
        $matchNumber = (int) $this->firstRoundMatches($bottomPlayers->count());
        $paired = $this->filterPlayersByRound($bottomPlayers, $matchNumber);

        $matches = [];
        $player1_id = null;

        foreach ($paired as $player) {
            if ($player1_id === null) {
                $player1_id = $player['id'];
                continue;
            }

            $player2_id = $player['id'];

            $match = TournamentMatch::updateOrCreate([
                'tournament_id' => $tournament->id,
                'round' => -$round,
                'suggested_play_order' => $playOrder,
            ], [
                'player1_id' => $this->resolvePlayer($player1_id, null, false),
                'player2_id' => $this->resolvePlayer($player2_id, null, false),
                'player1_prereq_match_id' => null,
                'player2_prereq_match_id' => null,
                'player1_is_prereq_match_loser' => false,
                'player2_is_prereq_match_loser' => false,
            ]);

            $this->rememberOutcome($match);
            $matches[] = $match;

            $playOrder++;
            $player1_id = null;
        }

        return $matches;
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