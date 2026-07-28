<?php

declare(strict_types=1);

namespace App\Services\Bracket;

use App\Enums\TournamentMatchStateEnum;
use App\Models\Tournament;
use App\Models\TournamentMatch;

class SingleEliminationService extends ModeService
{
    public function initialize(Tournament $tournament)
    {
        $players = $tournament->players()->checkedIn()->orderBy('seed', 'desc')->get();
        $numPlayers = $players->count();
        $numRounds = $this->calculateTotalRound($numPlayers);
        $rounds = [];
        $validMatchIds = [];
        $matchedWithWinner = [];

        for ($i = 1; $i <= $numRounds; $i++) {
            if ($i == 1) {
                $matchNumber = (int) $this->firstRoundMatches($numPlayers);
                $rounds[] = $this->filterPlayersByRound($players, $matchNumber);
            } else {
                $matchNumber = (int) $this->calculateMatchesCountInRound($numRounds, $i);
                $rounds[] = $this->filterPlayersByRound($players, $matchNumber);
            }
        }
        $suggestedPlayOrder = 1;
        $preRequestMatch = collect();

        foreach ($rounds as $roundKey => $round) {
            $player1_id = null;
            $player2_id = null;

            foreach ($round as $key => $player) {
                if ($player1_id === null) {
                    $player1_id = $player['id'];
                } elseif ($player2_id === null) {
                    $player2_id = $player['id'];
                }

                if ($player1_id !== null && $player2_id !== null) {
                    $player1PreReq = null;
                    $player2PreReq = null;

                    if ($player1_id == 0) {
                        $player1PreReq = $preRequestMatch->first();
                        $preRequestMatch = $preRequestMatch->slice(1)->values();
                    }

                    if ($player2_id == 0) {
                        $player2PreReq = $preRequestMatch->first();
                        $preRequestMatch = $preRequestMatch->slice(1)->values();
                    }

                    if (isset($matchedWithWinner[$player1PreReq])) {
                        $player1_id = $matchedWithWinner[$player1PreReq]->winner_id;
                    }
                    if (isset($matchedWithWinner[$player2PreReq])) {
                        $player2_id = $matchedWithWinner[$player2PreReq]->winner_id;
                    }

                    // Save the match directly and get its ID
                    $match = TournamentMatch::updateOrCreate([
                        'tournament_id' => $tournament->id,
                        'round' => $roundKey + 1,
                        'suggested_play_order' => $suggestedPlayOrder,
                    ], [
                        'player1_id' => $player1_id == 0 ? null : $player1_id,
                        'player2_id' => $player2_id == 0 ? null : $player2_id,
                        'player1_prereq_match_id' => $player1PreReq,
                        'player2_prereq_match_id' => $player2PreReq,
                    ]);

                    $validMatchIds[] = $match->id;

                    // Push real match ID instead of suggestedPlayOrder
                    $preRequestMatch->push($match->id);

                    // Reset for next match
                    $player1_id = null;
                    $player2_id = null;
                    $suggestedPlayOrder++;
                    if ($match->winner_id !== null) {
                        $matchedWithWinner[$match->id] = $match;
                    }
                }
            }
        }
        $tournament->matches()
            ->whereNotIn('id', $validMatchIds)
            ->delete();
        $this->updateMatchesState($tournament);
        return $tournament->matches()
            ->orderBy('suggested_play_order', 'asc')
            ->with(['player1', 'player2', 'matchScores'])
            ->get();
    }

    private function updateMatchesState(Tournament $tournament): void
    {
        $tournament->matches()
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->update(['state' => TournamentMatchStateEnum::OPEN]);

        $tournament->matches()
            ->whereNotNull('winner_id')
            ->update(['state' => TournamentMatchStateEnum::COMPLETE]);
        if (!$tournament->matches()->whereNull('winner_id')->exists()) {
            $this->assignFinalRanks($tournament);
        } else {
            $tournament->players()->update(['final_rank' => null]);
        }
    }

    private function assignFinalRanks(Tournament $tournament): void
    {
        $totalPlayers = $tournament->players()->count();
        $totalRounds = $this->calculateTotalRound($totalPlayers);

        // Step 1: Track elimination round for each player
        $eliminatedRounds = [];

        // Get all matches ordered by round descending (last to first)
        $matches = $tournament->matches()->orderByDesc('round')->get();

        foreach ($matches as $match) {
            // Skip matches without a winner yet
            if (!$match->winner_id) {
                continue;
            }

            // Determine loser
            $loserId = $match->player1_id === $match->winner_id
                ? $match->player2_id
                : $match->player1_id;

            // Only assign the *first* round they lost
            if (!isset($eliminatedRounds[$loserId])) {
                $eliminatedRounds[$loserId] = $match->round;
            }

            // Winner: if this is the final match, mark as champion
            if ($match->round === $totalRounds) {
                $winner = $tournament->players()->find($match->winner_id);
                $winner?->update(['final_rank' => 1]);

                // Loser of final = 2nd place
                $loser = $tournament->players()->find($loserId);
                $loser?->update(['final_rank' => 2]);
            }
        }

        // Step 2: Assign final ranks based on round of elimination
        foreach ($eliminatedRounds as $playerId => $roundEliminated) {
            // Skip finalists already handled
            $existingRank = $tournament->players()->find($playerId)?->final_rank;
            if ($existingRank) {
                continue;
            }

            // Players eliminated in same round share same rank
            // Convert round to rank (lower round = higher rank)
            // e.g., if totalRounds = 4 and eliminated in round 3 -> rank = 2^(totalRounds - 3) + 1
            // This produces ranks like 3, 5, 9, 17, etc.

            $relativeRound = $totalRounds - $roundEliminated;
            $rank = pow(2, $relativeRound) + 1;

            $tournament->players()->find($playerId)?->update(['final_rank' => $rank]);
        }
    }

    private function filterPlayersByRound(&$players, int $matchNumber)
    {
        $num = $matchNumber * 2;
        $data = $players->take($num);
        $players = collect($players->slice($num))->values();

        $missing = $num - $data->count();

        if ($missing > 0) {
            $data = $data->values(); // Reset index

            // Distribute byes: alternate inserting front and back
            for ($i = 0; $i < $missing; $i++) {
                if ($i % 2 === 0) {
                    // Insert from the front (after every other player)
                    $position = $i;
                } else {
                    // Insert from the back
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

        // Calculate the number of byes in the first round
        // Byes are the difference between the next power of two and the actual number of players
        $byes = $nextPowerOfTwo - $numPlayers;

        return ($numPlayers - $byes) / 2;
    }
}
