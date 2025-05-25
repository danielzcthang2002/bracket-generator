<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;

class TournamentMatchService
{


    public function generateMatches(int $tournamentId)
    {
        $tournament = Tournament::findOrFail($tournamentId);

        $tournamentMode = $tournament->mode_type->value;

        switch ($tournamentMode) {
            case 'single_elimination':
                return $this->generateSingleEliminationMatches($tournament);
            case 'double_elimination':
                return [];
            case 'round_robin':
                return [];
            default:
                throw new \Exception('Unsupported tournament mode: ' . $tournamentMode);
        }
    }

    public function generateSingleEliminationMatches(Tournament $tournament)
    {
        $players = $tournament->players()->checkedIn()->orderBy('seed')->get();
        $numPlayers = $players->count();
        $numRounds = $this->calculateTotalRound($numPlayers);
        $firstRoundMatches = $this->calculateFirstRoundMatches($numPlayers);
        $secondRoundMatchesCount = $this->calculateMatchesCountInRound($numRounds, 2);

        return [
            'total_players' => $numPlayers,
            'total_rounds' => $numRounds,
            'first_round_matches' => $firstRoundMatches,
            'second_round_matches_count' => $secondRoundMatchesCount,
        ];
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

    private function calculateFirstRoundMatches(int $numPlayers): int
    {
        return (int) ceil($numPlayers / 2);
    }
}
