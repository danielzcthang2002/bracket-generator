<?php

declare(strict_types=1);

namespace App\Services\Bracket;

class ModeService
{
    /**
     * Calculate the total number of rounds based on the number of players.
     *
     * @param int $numPlayers
     * @return int
     */
    public function calculateTotalRound(int $numPlayers): int
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

    /**
     * Calculate the number of matches in a given round.
     *
     * @param int $numRounds
     * @param int $roundNumber
     * @return int
     */
    public function calculateMatchesCountInRound(int $numRounds, int $roundNumber): int
    {
        // Round numbers start from 1 (first round) to N (final)
        return (int) pow(2, $numRounds - $roundNumber);
    }
}
