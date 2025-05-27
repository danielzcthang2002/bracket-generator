<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentMatch;

class TournamentMatchService
{

    public function generateMatches(int $tournamentId)
    {
        $tournament = Tournament::findOrFail($tournamentId);

        $tournamentMode = $tournament->mode_type->value;

        switch ($tournamentMode) {
            case 'single_elimination':
                return $this->initializeSingleElimination($tournament);
            case 'double_elimination':
                return [];
            case 'round_robin':
                return [];
            default:
                throw new \Exception('Unsupported tournament mode: ' . $tournamentMode);
        }
    }

    public function initializeSingleElimination(Tournament $tournament)
    {
        $players = $tournament->players()->checkedIn()->orderBy('seed', 'desc')->get();
        $numPlayers = $players->count();
        $numRounds = $this->calculateTotalRound($numPlayers);
        $rounds = [];

        for ($i = 1; $i <= $numRounds; $i++) {
            if ($i == 1) {
                $matchNumber = (int) $this->firstRoundMatches($numPlayers);
                $rounds[] = $this->filterPlayersByRound($players, $matchNumber);
            } else {
                $matchNumber = (int) $this->calculateMatchesCountInRound($numRounds, $i);
                $rounds[] = $this->filterPlayersByRound($players, $matchNumber);
            }
        }
        // return $rounds;

        $matches = [];
        $suggestedPlayOrder = 1;
        foreach ($rounds as $roundKey => $round) {
            $player1_id = null;
            $player2_id = null;
            // Loop throgh each player in the round
            // and insert them into a match
            foreach ($round as $key => $player) {
                if ($player1_id === null) {
                    $player1_id = $player['id'];
                } elseif ($player2_id === null) {
                    $player2_id = $player['id'];
                }

                if ($player1_id !== null && $player2_id !== null) {
                    // Create a match with the two players
                    $matches[] = [
                        'tournament_id' => $tournament->id,
                        'state' => 'pending',
                        'player1_id' => $player1_id == 0 ? null : $player1_id,
                        'player2_id' => $player2_id == 0 ? null : $player2_id,
                        'round' => $roundKey + 1,
                        'suggested_play_order' => $suggestedPlayOrder,
                    ];
                    // Reset player IDs for the next match
                    $player1_id = null;
                    $player2_id = null;
                    $suggestedPlayOrder++;
                }
            }
        }

        // foreach ($matches as $key => $match) {
        //     TournamentMatch::create($match);
        // }
        return TournamentMatch::where('tournament_id', $tournament->id)
            ->orderBy('round', 'asc')
            ->orderBy('suggested_play_order', 'asc')
            ->get();
    }

    private function filterPlayersByRound(&$players, int $matchNumber)
    {
        $num = $matchNumber * 2;
        $data = $players->take($num);
        $players = collect($players->slice($num))->values();

        // Ensure that the data has enough elements to match the number of matches
        // If there are not enough players, pad the array with default values
        if ($data->count() < $num) {
            $data = $data->pad($num, ['id' => 0]);
        }

        return $data;
    }

    private function calculateTotalRound(int $numPlayers): int
    {
        /**
         * Calculates the total number of rounds in a single elimination tournament.
         * The formula is based on the number of players, where each round halves the number of players.
         * For example, if there are 8 players, the rounds would be:
         * - Round 1: 8 players -> 4 matches
         * - Round 2: 4 players -> 2 matches
         * - Round 3: 2 players -> 1 match
         * Thus, the total number of rounds is log2(numPlayers).
         */
        return (int) ceil(log($numPlayers, 2));
    }

    private function calculateMatchesCountInRound(int $totalRounds, int $roundNumber): int
    {
        // Round numbers start from 1 (first round) to N (final)
        $roundsFromEnd = $totalRounds - $roundNumber + 1;
        return (int) (2 ** ($roundsFromEnd - 1));
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
