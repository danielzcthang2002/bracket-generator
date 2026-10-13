<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TournamentMatchStateEnum;
use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Services\Bracket\Contracts\ModeResolver;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    public function validateSwissRounds(Tournament $tournament, ?int $rounds): void
    {
        if ($rounds === $tournament->swiss_rounds) {
            return;
        }
        if ($tournament->status === TournamentStatus::ENDED) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'swiss_rounds' => 'Rounds cannot be changed after the tournament ends.',
            ]);
        }
        $minimum = max(1, (int) $tournament->matches()->max('round'));
        if ($rounds === null || $rounds < $minimum) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'swiss_rounds' => "Choose at least {$minimum} rounds to preserve existing matches.",
            ]);
        }
    }

    public function updateSwissRounds(string $openId, int $rounds): Tournament
    {
        return DB::transaction(function () use ($openId, $rounds): Tournament {
            $tournament = Tournament::query()->where('open_id', $openId)->lockForUpdate()->firstOrFail();
            if ($tournament->mode_type !== TournamentModeEnum::SWISS) {
                throw \Illuminate\Validation\ValidationException::withMessages(['swiss_rounds' => 'Rounds can only be set for Swiss tournaments.']);
            }
            $this->validateSwissRounds($tournament, $rounds);
            $tournament->update(['swiss_rounds' => $rounds]);
            if ($tournament->status === TournamentStatus::STARTED) {
                $this->resolver->resolve('swiss')->initialize($tournament);
            }

            return $tournament;
        });
    }

    public function __construct(
        private ModeResolver $resolver,
    ) {}

    public function createTournament(array $data): Tournament
    {
        DB::beginTransaction();
        try {
            $type = $data['mode_type'];
            $tournament = new Tournament;
            $tournament->status = TournamentStatus::PENDING;
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
            $tournament->ffa_heat_size = $data['ffa_heat_size'] ?? null;
            $tournament->ffa_advance_count = $data['ffa_advance_count'] ?? null;
            $tournament->head_to_head_count = $data['head_to_head_count'] ?? null;
            $tournament->rank_by = $data['rank_by'] ?? null;

            // Swiss mode specific fields
            if ($data['mode_type'] === 'swiss') {
                $tournament->points_per_match_win = $data['points_per_match_win'];
                $tournament->points_per_match_tie = $data['points_per_match_tie'];
                $tournament->points_per_set_win = $data['points_per_set_win'];
                $tournament->points_per_set_tie = $data['points_per_set_tie'];
                $tournament->points_per_bye = $data['points_per_bye'];
                $tournament->swiss_rounds = $data['swiss_rounds'];
            }

            $tournament->save();
            DB::commit();

            return $tournament;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to create tournament: '.$th->getMessage());
        }
    }

    public function updateTournament(string $openId, array $data): Tournament
    {
        $existing = Tournament::query()->where('open_id', $openId)->firstOrFail();
        if (($data['mode_type'] ?? $existing->mode_type->value) === 'swiss' && array_key_exists('swiss_rounds', $data)) {
            $this->validateSwissRounds($existing, isset($data['swiss_rounds']) ? (int) $data['swiss_rounds'] : null);
        }
        if (in_array($existing->status, [TournamentStatus::STARTED, TournamentStatus::ENDED], true)
            && isset($data['mode_type']) && $data['mode_type'] !== $existing->mode_type->value) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mode_type' => 'Mode cannot be changed after the tournament starts.',
            ]);
        }
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
            $tournament->ffa_heat_size = $data['ffa_heat_size'] ?? $tournament->ffa_heat_size;
            $tournament->head_to_head_count = $data['head_to_head_count'] ?? $tournament->head_to_head_count;
            $tournament->rank_by = $data['rank_by'] ?? $tournament->rank_by;
            if ($tournament->mode_type === TournamentModeEnum::SWISS && array_key_exists('swiss_rounds', $data)) {
                $tournament->swiss_rounds = $data['swiss_rounds'];
            }

            // Swiss mode specific fields
            if ($data['mode_type'] === 'swiss') {
                $tournament->points_per_match_win = $data['points_per_match_win'];
                $tournament->points_per_match_tie = $data['points_per_match_tie'];
                $tournament->points_per_set_win = $data['points_per_set_win'];
                $tournament->points_per_set_tie = $data['points_per_set_tie'];
                $tournament->points_per_bye = $data['points_per_bye'];
            }

            // Save the updated tournament
            if (! $tournament->save()) {
                throw new Exception('Failed to update tournament');
            }

            if ($tournament->mode_type === TournamentModeEnum::SWISS && $tournament->wasChanged('swiss_rounds')
                && $tournament->status === TournamentStatus::STARTED) {
                $this->resolver->resolve('swiss')->initialize($tournament);
            }

            DB::commit();

            return $tournament;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to update tournament: '.$th->getMessage());
        }
    }

    /**
     * Show a specific tournament by ID.
     */
    public function getTournamentById(string $openId): Tournament
    {
        $tournament = Tournament::query()
            ->where('open_id', $openId)->firstOrFail();

        return $tournament;
    }

    /**
     * Get all tournaments.
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

        return $this->resolver->resolve($tournament->mode_type->value)->initialize($tournament);
    }

    public function startTournament(string $openId): Tournament
    {
        return DB::transaction(function () use ($openId): Tournament {
            $tournament = Tournament::query()
                ->where('open_id', $openId)->lockForUpdate()->firstOrFail();
            $mode = $this->resolver->resolve($tournament->mode_type->value);
            if (!$mode->canStart($tournament)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'players' => match ($tournament->mode_type) {
                        TournamentModeEnum::SINGLE_ELIMINATION => 'Single elimination requires at least 2 checked-in players.',
                        TournamentModeEnum::ROUND_ROBIN => 'Round robin requires at least 2 checked-in players.',
                        TournamentModeEnum::SWISS => 'Swiss requires at least 2 checked-in players.',
                        TournamentModeEnum::FREE_FOR_ALL => 'Free-for-all requires at least 2 checked-in players.',
                        TournamentModeEnum::DOUBLE_ELIMINATION => $tournament->split_participant
                            ? 'Split-participant double elimination requires at least 3 checked-in players.'
                            : 'Double elimination requires at least 2 checked-in players.',
                        default => 'The checked-in player count does not meet this mode\'s start requirements.',
                    },
                ]);
            }
            $tournament->status = TournamentStatus::STARTED;
            $tournament->save();
            $mode->initialize($tournament);

            return $tournament;
        });
    }

    public function endTournament(string $openId): Tournament
    {
        $tournament = Tournament::query()
            ->where('open_id', $openId)->firstOrFail();
        $hasMatches = $tournament->matches()->exists();
        $hasIncompleteMatches = $tournament->matches()
            ->where('state', '!=', TournamentMatchStateEnum::COMPLETE)
            ->exists();
        if (! $hasMatches || $hasIncompleteMatches) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tournament' => 'All matches need to be completed before ending the tournament.',
            ]);
        }

        DB::beginTransaction();
        try {
            $tournament->status = TournamentStatus::ENDED;
            $tournament->save();
            DB::commit();

            return $tournament;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to end tournament: '.$th->getMessage());
        }
    }
}
