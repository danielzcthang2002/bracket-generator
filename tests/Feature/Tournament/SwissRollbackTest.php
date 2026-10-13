<?php

use App\Enums\TournamentMatchStateEnum;
use App\Models\MatchScore;
use App\Models\Tournament;
use App\Services\TournamentMatchService;
use App\Services\TournamentService;

beforeEach(function () {
    $this->freezeTime();
    $this->tournament = Tournament::create([
        'name' => 'Swiss rollback', 'open_id' => Tournament::generateOpenId(),
        'mode_type' => 'swiss', 'status' => 'started', 'swiss_rounds' => 3,
        'points_per_match_win' => 1, 'points_per_match_tie' => 0.5,
        'points_per_set_win' => 1, 'points_per_set_tie' => 0.5, 'points_per_bye' => 1,
    ]);
    foreach (range(1, 5) as $index) {
        $this->tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => true]);
    }
    $this->service = app(TournamentMatchService::class);
    $this->service->generateMatches($this->tournament->id);
    for ($round = 1; $round <= 3; $round++) {
        foreach ($this->tournament->matches()->where('round', $round)->whereNotNull('player2_id')->get() as $match) {
            $this->service->updateMatchScores($match->id, '3-1', $match->player1_id);
        }
    }
});

it('removes later rounds including scores and byes while preserving the corrected round', function (string $change) {
    $match = $this->tournament->matches()->where('round', 2)->whereNotNull('player2_id')->first();
    $preserved = $this->tournament->matches()->where('round', '<=', 2)->where('id', '!=', $match->id)
        ->with('matchScores')->get()->toArray();
    $laterIds = $this->tournament->matches()->where('round', 3)->pluck('id');
    $winner = match ($change) {
        'winner' => $match->player2_id,
        'tie' => 'tie',
        'undecided' => null,
        default => $match->player1_id,
    };
    $scores = $change === 'scores' ? '3-1,2-4' : '3-1';
    $this->service->updateMatchScores($match->id, $scores, $winner);
    expect($this->tournament->matches()->max('round'))->toBe(2)
        ->and(MatchScore::whereIn('tournament_match_id', $laterIds)->count())->toBe(0)
        ->and($this->tournament->players()->whereNotNull('final_rank')->count())->toBe(0)
        ->and($this->tournament->matches()->where('round', '<=', 2)->where('id', '!=', $match->id)
            ->with('matchScores')->get()->toArray())->toBe($preserved)
        ->and($match->fresh()->state)->toBe($change === 'undecided' ? TournamentMatchStateEnum::OPEN : TournamentMatchStateEnum::COMPLETE);

    app(TournamentService::class)->updateSwissRounds($this->tournament->open_id, 3);
    if ($change === 'undecided') {
        expect($this->tournament->matches()->max('round'))->toBe(2);
    } else {
        $newRound = $this->tournament->matches()->where('round', 3)->get();
        expect($newRound)->toHaveCount(3)
            ->and($newRound->whereNotNull('player2_id')->every(fn ($row) => $row->winner_id === null))->toBeTrue()
            ->and($newRound->whereNull('player2_id'))->toHaveCount(1);
    }
})->with(['winner', 'tie', 'undecided', 'scores']);

it('preserves later rounds when the edit has no effect on pairings', function (string $scores) {
    $match = $this->tournament->matches()->where('round', 1)->whereNotNull('player2_id')->first();
    $before = $this->tournament->matches()->where('round', '>', 1)->with('matchScores')->get()->toArray();
    $this->service->updateMatchScores($match->id, $scores, $match->winner_id);
    expect($this->tournament->matches()->where('round', '>', 1)->with('matchScores')->get()->toArray())->toBe($before);
})->with(['3-1', '4-2']);

it('does not reset later rounds for unweighted set changes', function () {
    $this->tournament->update(['points_per_set_win' => 0, 'points_per_set_tie' => 0]);
    $match = $this->tournament->matches()->where('round', 1)->whereNotNull('player2_id')->first();
    $before = $this->tournament->matches()->where('round', '>', 1)->with('matchScores')->get()->toArray();
    $this->service->updateMatchScores($match->id, '1-3', $match->winner_id);
    expect($this->tournament->matches()->where('round', '>', 1)->with('matchScores')->get()->toArray())->toBe($before);
});

it('updates the latest round without deleting matches and clears ranks when reopened', function () {
    $ids = $this->tournament->matches()->pluck('id')->all();
    $match = $this->tournament->matches()->where('round', 3)->whereNotNull('player2_id')->first();
    $this->service->updateMatchScores($match->id, '1-3', $match->player2_id);
    expect($this->tournament->matches()->pluck('id')->all())->toBe($ids)
        ->and($this->tournament->players()->whereNotNull('final_rank')->count())->toBe(5);
    $this->service->updateMatchScores($match->id, '1-3', null);
    expect($match->fresh()->state)->toBe(TournamentMatchStateEnum::OPEN)
        ->and($this->tournament->players()->whereNotNull('final_rank')->count())->toBe(0);
});

it('keeps all rounds intact if the correction fails validation', function () {
    $before = $this->tournament->matches()->with('matchScores')->get()->toArray();
    $match = $this->tournament->matches()->whereNotNull('player2_id')->first();
    expect(fn () => $this->service->updateMatchScores($match->id, 'invalid', $match->player2_id))
        ->toThrow(InvalidArgumentException::class);
    expect($this->tournament->matches()->with('matchScores')->get()->toArray())->toBe($before);
});
