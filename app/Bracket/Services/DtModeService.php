<?php

declare(strict_types=1);

namespace App\Bracket\Services;

use App\Bracket\Models\DtTournament;
use App\Bracket\Models\DtPlayer;
use Illuminate\Database\Eloquent\Collection;

abstract class DtModeService
{
    abstract public function canStart(DtTournament $tournament): bool;

    abstract public function initialize(DtTournament $tournament);

    abstract public function previewBracket(DtTournament $tournament): array;

    /**
     * Retrieve players in seed order, requiring check-in only when configured.
     *
     * @param  array<int>|null  $playerIds
     * @return Collection<int, DtPlayer>
     */
    protected function getPlayers(DtTournament $tournament, ?array $playerIds = null): Collection
    {
        return $tournament->players()
            ->when($tournament->require_check_in, fn ($query) => $query->checkedIn())
            ->when($playerIds !== null, fn ($query) => $query->whereIn('id', $playerIds))
            ->orderBy('seed')
            ->orderBy('id')
            ->get();
    }

    protected function previewPlayer(DtPlayer $player): array
    {
        return [
            'type' => 'player',
            'id' => $player->id,
            'name' => $player->name,
            'seed' => $player->seed,
        ];
    }

    protected function previewTbd(string $label = 'TBD', ?int $prerequisite = null): array
    {
        return [
            'type' => 'tbd',
            'label' => $label,
            'prereq_suggested_play_order' => $prerequisite,
        ];
    }
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
