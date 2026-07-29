<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TournamentMatchStateEnum;
use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Services\Bracket\DoubleEliminationService;
use App\Services\Bracket\RoundRobinService;
use App\Services\Bracket\SingleEliminationService;
use App\Services\Bracket\SwissService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TournamentService
{


    /**
     * @param array $data
     * @return Tournament
     */
    public function createTournament(array $data): Tournament
    {
        DB::beginTransaction();
        try {
            $type = str_replace(' ', '_', $data['tournament_type']);
            $tournament = new Tournament();
            $tournament->name = $data['name'];
            $tournament->open_id = Tournament::generateOpenId();
            $tournament->mode_type = TournamentModeEnum::from($type);
            $tournament->description = $data['description'] ?? null;
            $tournament->start_at = $data['start_at'] ?? null;
            $tournament->end_at = $data['end_at'] ?? null;
            $tournament->require_check_in = $data['require_check_in'] ?? false;
            $tournament->check_in_time = $data['check_in_time'] ?? null;
            $tournament->max_entry = $data['max_entry'] ?? null;
            $tournament->split_participant = $data['split_participant'] ?? false;
            $tournament->participants_per_match = $data['participants_per_match'] ?? null;
            $tournament->head_to_head_count = $data['head_to_head_count'] ?? null;
            $tournament->rank_by = $data['rank_by'] ?? null;

            // Swiss mode specific fields
            if ($data['tournament_type'] === 'swiss') {
                $tournament->points_per_match_win = $data['points_per_match_win'];
                $tournament->points_per_match_tie = $data['points_per_match_tie'];
                $tournament->points_per_set_win = $data['points_per_set_win'];
                $tournament->points_per_set_tie = $data['points_per_set_tie'];
                $tournament->points_per_bye = $data['points_per_bye'];
            }

            $tournament->save();
            DB::commit();
            return $tournament;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to create tournament: ' . $th->getMessage());
        }
    }

    public function updateTournament(string $openId, array $data): Tournament
    {
        DB::beginTransaction();
        try {
            $tournament = Tournament::query()
                ->where('open_id', $openId)->firstOrFail();
            $tournament->name = $data['name'] ?? $tournament->name;
            $tournament->mode_type = $data['mode_type'] ?? $tournament->mode_type;
            $tournament->description = $data['description'] ?? $tournament->description;
            $tournament->start_at = $data['start_at'] ?? $tournament->start_at;
            $tournament->end_at = $data['end_at'] ?? $tournament->end_at;
            $tournament->require_check_in = $data['require_check_in'] ?? $tournament->require_check_in;
            $tournament->check_in_time = $data['check_in_time'] ?? $tournament->check_in_time;
            $tournament->max_entry = $data['max_entry'] ?? $tournament->max_entry;
            $tournament->split_participant = $data['split_participant'] ?? $tournament->split_participant;
            $tournament->participants_per_match = $data['participants_per_match'] ?? $tournament->participants_per_match;
            $tournament->head_to_head_count = $data['head_to_head_count'] ?? $tournament->head_to_head_count;
            $tournament->rank_by = $data['rank_by'] ?? $tournament->rank_by;

            // Swiss mode specific fields
            if ($data['mode_type'] === 'swiss') {
                $tournament->points_per_match_win = $data['points_per_match_win'];
                $tournament->points_per_match_tie = $data['points_per_match_tie'];
                $tournament->points_per_set_win = $data['points_per_set_win'];
                $tournament->points_per_set_tie = $data['points_per_set_tie'];
                $tournament->points_per_bye = $data['points_per_bye'];
            }

            // Save the updated tournament
            if (!$tournament->save()) {
                throw new Exception('Failed to update tournament');
            }

            DB::commit();
            return $tournament;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to update tournament: ' . $th->getMessage());
        }
    }


    /**
     * Show a specific tournament by ID.
     *
     * @param string $openId
     * @return Tournament
     */
    public function getTournamentById(string $openId): Tournament
    {
        $tournament = Tournament::query()
            ->where('open_id', $openId)->firstOrFail();
        return $tournament;
    }

    /**
     * Get all tournaments.
     *
     * @return Collection
     */
    public function getTournaments(): Collection
    {
        $tournaments = Tournament::all();
        return $tournaments;
    }

    public function generateMatches(string $openId)
    {
        $tournament = Tournament::query()
            ->where('open_id', $openId)->firstOrFail();
        $singleEliminationService = new SingleEliminationService();

        return $singleEliminationService->initialize($tournament);
    }

    public function startTournament(string $openId): Tournament
    {
        DB::beginTransaction();
        try {
            $tournament = Tournament::query()
                ->where('open_id', $openId)->firstOrFail();
            $tournament->status = TournamentStatus::STARTED;
            $tournament->save();
            $this->handleTournamentModeSpecificLogic($tournament);
            DB::commit();
            return $tournament;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to start tournament: ' . $th->getMessage());
        }
    }

    public function endTournament(string $openId): Tournament
    {
        $tournament = Tournament::query()
            ->where('open_id', $openId)->firstOrFail();
        $allMatchesCompleted = $tournament
            ->matches()
            ->where('state', TournamentMatchStateEnum::COMPLETE)
            ->exists();
        if (! $allMatchesCompleted) throw new Exception("All matches need to be completed to end a tournament.", 1);

        DB::beginTransaction();
        try {
            $tournament->status = TournamentStatus::ENDED;
            $tournament->save();
            DB::commit();
            return $tournament;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to end tournament: ' . $th->getMessage());
        }
    }

    private function handleTournamentModeSpecificLogic(Tournament $tournament): void
    {
        if ($tournament->mode_type === TournamentModeEnum::SINGLE_ELIMINATION) {
            $singleEliminationService = new SingleEliminationService();
            $singleEliminationService->initialize($tournament);
        } elseif ($tournament->mode_type === TournamentModeEnum::DOUBLE_ELIMINATION) {
            $doubleEliminationService = new DoubleEliminationService();
            $doubleEliminationService->initialize($tournament);
        } elseif ($tournament->mode_type === TournamentModeEnum::ROUND_ROBIN) {
            $roundRobinService = new RoundRobinService();
            $roundRobinService->initialize($tournament);
        } elseif ($tournament->mode_type === TournamentModeEnum::SWISS) {
            $roundRobinService = new SwissService();
            $roundRobinService->initialize($tournament);
        }
    }
}
