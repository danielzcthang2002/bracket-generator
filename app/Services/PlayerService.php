<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\Tournament;
use App\TournamentStatus;
use Exception;
use Illuminate\Support\Facades\DB;

class PlayerService
{

    public function getPlayersByTournamentId(string $openId): \Illuminate\Database\Eloquent\Collection
    {
        $tournament = Tournament::where('open_id', $openId)->firstOrFail();
        return Player::where('tournament_id', $tournament->id)->get();
    }

    private function tournamentStartedBlock(Tournament $tournament): void
    {
        if ($tournament->status == TournamentStatus::STARTED) {
            throw new Exception('Tournament has already started. Cannot modify players.');
        }
    }

    /**
     * Create a new player.
     *
     * @param array $data
     * @param string $openId
     * @return Player
     */
    public function createPlayer(array $data, string $openId): Player
    {
        DB::beginTransaction();
        try {
            $tournament = Tournament::where('open_id', $openId)->firstOrFail();
            $player = new Player();

            $player->name = $data['name'];
            $player->tournament_id = $tournament->id;
            $player->checked_in = $data['checked_in'] ?? true;
            $player->checked_in_at = $data['checked_in_at'] ?? null;
            $this->tournamentStartedBlock($tournament);

            $this->assignSeed($player, $tournament->id);

            $player->save();
            DB::commit();
            return $player;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to create player: ' . $th->getMessage());
        }
    }

    public function processCheckedin(string $openId): void
    {
        $tournament = Tournament::where('open_id', $openId)->firstOrFail();
        $tournamentId = $tournament->id;
        $uncheckedCount = Player::where('tournament_id', $tournamentId)
            ->where('checked_in', false)
            ->count();
        $players = Player::where('tournament_id', $tournamentId)
            ->where('checked_in', false)
            ->delete();

        if ($uncheckedCount > 0) {
            $this->reassignSeeds($tournamentId);
        }
    }




    public function removePlayer(int $playerId): Player
    {
        $player = Player::findOrFail($playerId);
        $tournament = Tournament::findOrFail($player->tournament_id);
        $this->tournamentStartedBlock($tournament);
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

        if ($players->isEmpty()) {
            return; // No players to reassign seeds for
        }
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


    public function checkInPlayer(string $openId, int $playerId): Player
    {
        $tournament = Tournament::where('open_id', $openId)->firstOrFail();
        $player = Player::where('tournament_id', $tournament->id)
            ->where('id', $playerId)
            ->firstOrFail();

        $this->tournamentStartedBlock($tournament);

        $player->checked_in = true;
        $player->checked_in_at = now();
        $player->save();

        return $player;
    }

    public function undoCheckInPlayer(string $openId, int $playerId): Player
    {
        $tournament = Tournament::where('open_id', $openId)->firstOrFail();
        $player = Player::where('tournament_id', $tournament->id)
            ->where('id', $playerId)
            ->firstOrFail();

        $this->tournamentStartedBlock($tournament);

        $player->checked_in = false;
        $player->checked_in_at = null;
        $player->save();

        return $player;
    }
}
