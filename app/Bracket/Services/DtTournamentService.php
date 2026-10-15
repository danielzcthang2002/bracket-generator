<?php

declare(strict_types=1);

namespace App\Bracket\Services;

use App\Bracket\Enums\DtTournamentMatchStateEnum;
use App\Bracket\Enums\DtTournamentModeEnum;
use App\Bracket\Enums\DtTournamentStatus;
use App\Bracket\Models\DtTournament;
use App\Bracket\Contracts\DtModeResolver;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DtTournamentService
{
    public function validateSwissRounds(DtTournament $tournament, ?int $rounds): void
    {
        if ($rounds === $tournament->swiss_rounds) {
            return;
        }
        if ($tournament->status === DtTournamentStatus::ENDED) {
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

    public function updateSwissRounds(string $openId, int $rounds): DtTournament
    {
        return DB::transaction(function () use ($openId, $rounds): DtTournament {
            $tournament = DtTournament::query()->where('open_id', $openId)->lockForUpdate()->firstOrFail();
            if ($tournament->mode_type !== DtTournamentModeEnum::SWISS) {
                throw \Illuminate\Validation\ValidationException::withMessages(['swiss_rounds' => 'Rounds can only be set for Swiss tournaments.']);
            }
            $this->validateSwissRounds($tournament, $rounds);
            $tournament->update(['swiss_rounds' => $rounds]);
            if ($tournament->status === DtTournamentStatus::STARTED) {
                $this->resolver->resolve('swiss')->initialize($tournament);
            }

            return $tournament;
        });
    }

    public function __construct(
        private DtModeResolver $resolver,
    ) {}

    public function createTournament(array $data): DtTournament
    {
        DB::beginTransaction();
        try {
            $type = $data['mode_type'];
            $tournament = new DtTournament;
            $tournament->status = DtTournamentStatus::PENDING;
            $tournament->name = $data['name'];
            $tournament->open_id = DtTournament::generateOpenId();
            $tournament->mode_type = DtTournamentModeEnum::from($type);
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

    public function validateCheckInRequirement(DtTournament $tournament, array $data): void
    {
        if (in_array($tournament->status, [DtTournamentStatus::STARTED, DtTournamentStatus::ENDED], true)
            && array_key_exists('require_check_in', $data)
            && (bool) $data['require_check_in'] !== $tournament->require_check_in) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'require_check_in' => 'Check-in requirement cannot be changed after the tournament starts.',
            ]);
        }
    }

    public function updateTournament(string $openId, array $data): DtTournament
    {
        $existing = DtTournament::query()->where('open_id', $openId)->firstOrFail();
        $this->validateCheckInRequirement($existing, $data);
        if (($data['mode_type'] ?? $existing->mode_type->value) === 'swiss' && array_key_exists('swiss_rounds', $data)) {
            $this->validateSwissRounds($existing, isset($data['swiss_rounds']) ? (int) $data['swiss_rounds'] : null);
        }
        if (in_array($existing->status, [DtTournamentStatus::STARTED, DtTournamentStatus::ENDED], true)
            && isset($data['mode_type']) && $data['mode_type'] !== $existing->mode_type->value) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mode_type' => 'Mode cannot be changed after the tournament starts.',
            ]);
        }
        DB::beginTransaction();
        try {
            $tournament = DtTournament::query()
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
            if ($tournament->mode_type === DtTournamentModeEnum::SWISS && array_key_exists('swiss_rounds', $data)) {
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

            if ($tournament->mode_type === DtTournamentModeEnum::SWISS && $tournament->wasChanged('swiss_rounds')
                && $tournament->status === DtTournamentStatus::STARTED) {
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
    public function getTournamentById(string $openId): DtTournament
    {
        $tournament = DtTournament::query()
            ->where('open_id', $openId)->firstOrFail();

        return $tournament;
    }

    /**
     * Get all tournaments.
     */
    public function getTournaments(): Collection
    {
        $tournaments = DtTournament::all();

        return $tournaments;
    }

    public function generateMatches(string $openId)
    {
        $tournament = DtTournament::query()
            ->where('open_id', $openId)->firstOrFail();

        return $this->resolver->resolve($tournament->mode_type->value)->initialize($tournament);
    }

    public function startTournament(string $openId): DtTournament
    {
        return DB::transaction(function () use ($openId): DtTournament {
            $tournament = DtTournament::query()
                ->where('open_id', $openId)->lockForUpdate()->firstOrFail();
            $mode = $this->resolver->resolve($tournament->mode_type->value);
            if (!$mode->canStart($tournament)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'players' => match ($tournament->mode_type) {
                        DtTournamentModeEnum::SINGLE_ELIMINATION => 'Single elimination requires at least 2 checked-in players.',
                        DtTournamentModeEnum::ROUND_ROBIN => 'Round robin requires at least 2 checked-in players.',
                        DtTournamentModeEnum::SWISS => 'Swiss requires at least 2 checked-in players.',
                        DtTournamentModeEnum::FREE_FOR_ALL => 'Free-for-all requires at least 2 checked-in players.',
                        DtTournamentModeEnum::DOUBLE_ELIMINATION => $tournament->split_participant
                            ? 'Split-participant double elimination requires at least 3 checked-in players.'
                            : 'Double elimination requires at least 2 checked-in players.',
                        default => 'The checked-in player count does not meet this mode\'s start requirements.',
                    },
                ]);
            }
            $tournament->status = DtTournamentStatus::STARTED;
            $tournament->save();
            $mode->initialize($tournament);

            return $tournament;
        });
    }

    public function rollbackTournament(string $openId, string $expectedStatus): DtTournament
    {
        return DB::transaction(function () use ($openId, $expectedStatus): DtTournament {
            $tournament = DtTournament::query()
                ->where('open_id', $openId)->lockForUpdate()->firstOrFail();

            if ($tournament->status->value !== $expectedStatus
                || ! in_array($tournament->status, [DtTournamentStatus::STARTED, DtTournamentStatus::ENDED], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'Tournament status has changed or cannot be rolled back. Refresh and try again.',
                ]);
            }

            if ($tournament->status === DtTournamentStatus::STARTED) {
                $tournament->matches()->delete();
                $tournament->players()->update(['final_rank' => null]);
                $tournament->status = DtTournamentStatus::PENDING;
            } else {
                $tournament->status = DtTournamentStatus::STARTED;
            }
            $tournament->save();

            return $tournament;
        });
    }

    public function endTournament(string $openId): DtTournament
    {
        $tournament = DtTournament::query()
            ->where('open_id', $openId)->firstOrFail();
        $hasMatches = $tournament->matches()->exists();
        $hasIncompleteMatches = $tournament->matches()
            ->where('state', '!=', DtTournamentMatchStateEnum::COMPLETE)
            ->exists();
        if (! $hasMatches || $hasIncompleteMatches) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tournament' => 'All matches need to be completed before ending the tournament.',
            ]);
        }

        DB::beginTransaction();
        try {
            $tournament->status = DtTournamentStatus::ENDED;
            $tournament->save();
            DB::commit();

            return $tournament;
        } catch (Exception $th) {
            DB::rollBack();
            throw new Exception('Failed to end tournament: '.$th->getMessage());
        }
    }
}
