<?php

use App\Bracket\Enums\DtTournamentMatchStateEnum;
use App\Bracket\Models\DtTournament;
use App\Bracket\Models\DtTournamentMatch;
use App\Bracket\Services\DtTournamentMatchService;

function ffaRollbackPayload(DtTournamentMatch $match): array
{
    return $match->participants()->orderBy('position')->get()->values()
        ->map(fn ($row, $index) => ['id' => $row->id, 'rank' => $index + 1, 'score' => 100 - $index])->all();
}

function finishFfaRollback(DtTournament $tournament): void
{
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $match = $tournament->matches()->where('state', 'open')->orderBy('suggested_play_order')->first();
        if (!$match) {
            return;
        }
        app(DtTournamentMatchService::class)->updateFfaMatchScores($match->id, ffaRollbackPayload($match));
    }
    throw new RuntimeException('Bracket did not finish');
}

beforeEach(function () {
    $this->freezeTime();
    $this->tournament = DtTournament::create([
        'name' => 'FFA rollback', 'open_id' => DtTournament::generateOpenId(),
        'mode_type' => 'free_for_all', 'status' => 'started',
        'ffa_heat_size' => 4, 'ffa_advance_count' => 2,
    ]);
    foreach (range(1, 16) as $index) {
        $this->tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => true, 'seed' => $index]);
    }
    $this->service = app(DtTournamentMatchService::class);
    $this->service->generateMatches($this->tournament->id);
    finishFfaRollback($this->tournament);
});

it('rebuilds affected heats and clears dependent results while preserving unrelated heats', function () {
    $first = $this->tournament->matches()->where('round', 1)->orderBy('suggested_play_order')->first();
    $otherFirst = $this->tournament->matches()->where('round', 1)->where('id', '!=', $first->id)->with('participants')->get()->toArray();
    $secondRound = $this->tournament->matches()->where('round', 2)->orderBy('suggested_play_order')->with('participants')->get();
    $final = $this->tournament->matches()->where('round', 3)->first();
    $payload = ffaRollbackPayload($first);
    // Swap the second qualifier with the third-place finisher.
    [$payload[1]['rank'], $payload[2]['rank']] = [3, 2];
    $newQualifier = $first->participants()->orderBy('position')->get()[2]->player_id;
    $this->service->updateFfaMatchScores($first->id, $payload);
    expect($first->fresh()->state)->toBe(DtTournamentMatchStateEnum::COMPLETE)
        ->and($this->tournament->matches()->where('round', 1)->where('id', '!=', $first->id)->with('participants')->get()->toArray())->toBe($otherFirst)
        ->and($final->fresh()->state)->toBe(DtTournamentMatchStateEnum::PENDING)
        ->and($final->participants()->count())->toBe(0)
        ->and($this->tournament->players()->whereNotNull('final_rank')->count())->toBe(0);
    $unchanged = 0;
    foreach ($secondRound as $before) {
        $after = $before->fresh('participants');
        if ($before->participants->pluck('player_id')->all() === $after->participants->pluck('player_id')->all()) {
            $unchanged++;
            expect($after->participants->toArray())->toBe($before->participants->toArray())
                ->and($after->state)->toBe(DtTournamentMatchStateEnum::COMPLETE);
        } else {
            expect($after->state)->toBe(DtTournamentMatchStateEnum::OPEN)
                ->and($after->participants->every(fn ($row) => $row->rank === null && $row->score === null && !$row->is_winner))->toBeTrue();
        }
    }
    expect($unchanged)->toBe(1)
        ->and($this->tournament->matches()->where('round', 2)->whereHas('participants', fn ($q) => $q->where('player_id', $newQualifier))->exists())->toBeTrue();
    finishFfaRollback($this->tournament);
    expect($this->tournament->players()->whereNotNull('final_rank')->count())->toBe(16)
        ->and($final->fresh()->state)->toBe(DtTournamentMatchStateEnum::COMPLETE);
});

it('preserves later results for score edits and eliminated-player rank changes', function (bool $swap) {
    $first = $this->tournament->matches()->where('round', 1)->first();
    $before = $this->tournament->matches()->where('round', '>', 1)->with('participants')->get()->toArray();
    $payload = ffaRollbackPayload($first);
    $payload[0]['score'] = 999;
    if ($swap) {
        [$payload[2]['rank'], $payload[3]['rank']] = [4, 3];
    }
    $this->service->updateFfaMatchScores($first->id, $payload);
    expect($this->tournament->matches()->where('round', '>', 1)->with('participants')->get()->toArray())->toBe($before);
})->with([false, true]);

it('clears later slots when the same qualifiers exchange finishing positions', function () {
    $first = $this->tournament->matches()->where('round', 1)->first();
    $payload = ffaRollbackPayload($first);
    [$payload[0]['rank'], $payload[1]['rank']] = [2, 1];
    $this->service->updateFfaMatchScores($first->id, $payload);
    expect($this->tournament->matches()->where('round', 2)->where('state', 'open')->count())->toBeGreaterThan(0)
        ->and($this->tournament->matches()->where('round', 3)->first()->state)->toBe(DtTournamentMatchStateEnum::PENDING);
});

it('recalculates final ranks when the final heat is corrected', function () {
    $final = $this->tournament->matches()->orderByDesc('round')->first();
    $rows = $final->participants()->orderBy('position')->get();
    $payload = ffaRollbackPayload($final);
    [$payload[0]['rank'], $payload[1]['rank']] = [2, 1];
    $this->service->updateFfaMatchScores($final->id, $payload);
    expect($this->tournament->players()->find($rows[1]->player_id)->final_rank)->toBe(1)
        ->and($this->tournament->players()->find($rows[0]->player_id)->final_rank)->toBe(2);
});

it('rejects duplicate participant submissions without changing the bracket', function () {
    $before = $this->tournament->matches()->with('participants')->get()->toArray();
    $first = $this->tournament->matches()->first();
    $payload = ffaRollbackPayload($first);
    $payload[1]['id'] = $payload[0]['id'];
    expect(fn () => $this->service->updateFfaMatchScores($first->id, $payload))->toThrow(InvalidArgumentException::class);
    expect($this->tournament->matches()->with('participants')->get()->toArray())->toBe($before);
});

it('handles corrections with undersized heats and byes', function (int $count, int $heatSize) {
    $tournament = DtTournament::create([
        'name' => 'Uneven FFA', 'open_id' => DtTournament::generateOpenId(),
        'mode_type' => 'free_for_all', 'status' => 'started', 'ffa_heat_size' => $heatSize,
    ]);
    foreach (range(1, $count) as $index) {
        $tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => true]);
    }
    $this->service->generateMatches($tournament->id);
    finishFfaRollback($tournament);
    $first = $tournament->matches()->where('round', 1)->has('participants', '>', 1)->first();
    $payload = ffaRollbackPayload($first);
    [$payload[0]['rank'], $payload[count($payload) - 1]['rank']] = [count($payload), 1];
    $this->service->updateFfaMatchScores($first->id, $payload);
    expect($tournament->players()->whereNotNull('final_rank')->count())->toBe(0);
    finishFfaRollback($tournament);
    expect($tournament->players()->whereNotNull('final_rank')->count())->toBe($count)
        ->and($tournament->matches()->where('state', '!=', 'complete')->count())->toBe(0);
})->with([[3, 2], [9, 4], [5, 3]]);
