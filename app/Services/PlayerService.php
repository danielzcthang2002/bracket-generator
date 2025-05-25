<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use Exception;
use Illuminate\Support\Facades\DB;

class PlayerService
{

    public function getPlayersByTournamentId(int $tournamentId): \Illuminate\Database\Eloquent\Collection
    {
        return Player::where('tournament_id', $tournamentId)->get();
    }

    /**
     * Create a new player.
     *
     * @param array $data
     * @return Player
     */
    public function createPlayer(array $data): Player
    {
        DB::beginTransaction();
        try {
            $player = new Player();

            $player->name = $data['name'];
            $player->tournament_id = $data['tournament_id'];
            $player->checked_in = $data['checked_in'] ?? false;
            $player->checked_in_at = $data['checked_in_at'] ?? null;

            $this->assignSeed($player, $data['tournament_id']);

            $player->save();
            DB::commit();
            return $player;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to create player: ' . $th->getMessage());
        }
    }




    public function removePlayer(int $playerId): Player
    {
        $player = Player::findOrFail($playerId);
        $deletedPlayer = $player->replicate();
        $player->delete();
        $this->reassignSeeds($player->tournament_id);
        return $deletedPlayer;
    }

    // ===== Helper Methods =====
    /**This method is used to assign a seed to a new player based on the latest seed in the tournament.*/
    private function assignSeed(Player $player, int $tournamentId): void
    {
        // Lock the tournament players table to prevent race conditions
        $latestSeed = Player::where('tournament_id', $tournamentId)
            ->lockForUpdate()
            ->orderByDesc('seed')
            ->value('seed');

        $player->seed = ($latestSeed ?? 0) + 1;
    }

    /**
     * Reassign seeds for all players in a tournament after a player is removed.
     * @param int $tournamentId
     * @return void
     */
    private function reassignSeeds(int $tournamentId): void
    {
        // Get all players in memory (1 query)
        $players = Player::where('tournament_id', $tournamentId)
            ->orderBy('seed')
            ->get(['id']); // Only select IDs

        // Build case statement for bulk update (1 query)
        $cases = $players->map(
            fn($player, $index) =>
            "WHEN id = {$player->id} THEN " . ($index + 1)
        )->implode(' ');

        DB::update(
            "UPDATE players SET seed = CASE $cases END
         WHERE tournament_id = ?",
            [$tournamentId]
        );
    }
}
