<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use Exception;
use Illuminate\Support\Facades\DB;

class PlayerService
{
    public function getPlayersByTournamentId(string $openId): \Illuminate\Database\Eloquent\Collection
    {
        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();

        return Player::query()->where('tournament_id', $tournament->id)->get();
    }

    private function tournamentStartedBlock(Tournament $tournament): void
    {
        if (in_array($tournament->status, [TournamentStatus::STARTED, TournamentStatus::ENDED], true)) {
            throw new Exception('Tournament has already started. Cannot modify players.');
        }
    }

    /**
     * Create a new player.
     */
    public function createPlayer(array $data, string $openId): Player
    {
        DB::beginTransaction();
        try {
            $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();
            $player = new Player;

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
            throw new Exception('Failed to create player: '.$th->getMessage());
        }
    }

    public function processCheckedin(string $openId): void
    {
        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();
        $tournamentId = $tournament->id;
        $uncheckedCount = Player::query()->where('tournament_id', $tournamentId)
            ->where('checked_in', false)
            ->count('*');
        Player::query()->where('tournament_id', $tournamentId)
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

    /**
     * @param  array<int>  $playerIds
     */
    public function removePlayers(string $openId, array $playerIds): int
    {
        return DB::transaction(function () use ($openId, $playerIds): int {
            $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();
            $this->tournamentStartedBlock($tournament);
            $players = $tournament->players()->whereKey($playerIds)->lockForUpdate()->get();

            if ($players->count() !== count(array_unique($playerIds))) {
                throw new \InvalidArgumentException('One or more players do not belong to this tournament.');
            }

            $deleted = $tournament->players()->whereKey($playerIds)->delete();
            $this->reassignSeeds($tournament->id);

            return $deleted;
        });
    }

    public function swapSeeds(int $firstPlayerId, int $secondPlayerId): void
    {
        DB::transaction(function () use ($firstPlayerId, $secondPlayerId): void {
            $players = Player::query()
                ->whereKey([$firstPlayerId, $secondPlayerId])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $firstPlayer = $players->get($firstPlayerId);
            $secondPlayer = $players->get($secondPlayerId);

            if (! $firstPlayer || ! $secondPlayer) {
                throw new \InvalidArgumentException('One or more players do not exist.');
            }

            if ($firstPlayer->tournament_id !== $secondPlayer->tournament_id) {
                throw new \InvalidArgumentException('Players must belong to the same tournament.');
            }

            $tournament = Tournament::query()->findOrFail($firstPlayer->tournament_id);
            $this->tournamentStartedBlock($tournament);

            if ($firstPlayer->is($secondPlayer)) {
                return;
            }

            $firstSeed = $firstPlayer->seed;
            $firstPlayer->seed = $secondPlayer->seed;
            $secondPlayer->seed = $firstSeed;

            $firstPlayer->save();
            $secondPlayer->save();
        });
    }

    // ===== Helper Methods =====
    /**This method is used to assign a seed to a new player based on the latest seed in the tournament.*/
    private function assignSeed(Player $player, int $tournamentId): void
    {
        // Lock the tournament players table to prevent race conditions
        $latestSeed = Player::query()->where('tournament_id', $tournamentId)
            ->lockForUpdate()
            ->orderByDesc('seed')
            ->value('seed');

        $player->seed = ($latestSeed ?? 0) + 1;
    }

    /**
     * Reassign seeds for all players in a tournament after a player is removed.
     */
    private function reassignSeeds(int $tournamentId): void
    {
        // Get all players in memory (1 query)
        $players = Player::query()->where('tournament_id', $tournamentId)
            ->orderBy('seed', 'asc')
            ->get(['id']); // Only select IDs

        if ($players->isEmpty()) {
            return; // No players to reassign seeds for
        }
        // Build case statement for bulk update (1 query)
        $cases = $players->map(
            fn ($player, $index) => "WHEN id = {$player->id} THEN ".($index + 1)
        )->implode(' ');

        DB::update(
            "UPDATE players SET seed = CASE $cases END
         WHERE tournament_id = ?",
            [$tournamentId]
        );
    }

    public function checkInPlayer(string $openId, int $playerId): Player
    {
        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();
        $player = Player::query()->where('tournament_id', $tournament->id)
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
        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();
        $player = Player::query()->where('tournament_id', $tournament->id)
            ->where('id', $playerId)
            ->firstOrFail();

        $this->tournamentStartedBlock($tournament);

        $player->checked_in = false;
        $player->checked_in_at = null;
        $player->save();

        return $player;
    }
}
