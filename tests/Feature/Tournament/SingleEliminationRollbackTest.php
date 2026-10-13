<?php

use App\Enums\TournamentMatchStateEnum;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\TournamentMatchService;

beforeEach(function () {
    $this->freezeTime();
    $this->tournament = Tournament::create([
        'name' => 'Rollback Cup',
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => 'single_elimination',
        'status' => 'open',
    ]);
    $this->service = app(TournamentMatchService::class);
});

function completeRollbackBracket($test, int $players): void
{
    foreach (range(1, $players) as $index) {
        Player::create([
            'name' => 'Player '.$index,
            'tournament_id' => $test->tournament->id,
            'checked_in' => true,
        ]);
    }
    $matches = $test->service->generateMatches($test->tournament->id);
    foreach ($matches as $match) {
        $match->refresh();
        $test->service->updateMatchScores($match->id, '3-1', $match->player1_id);
        $match->update(['completed_at' => now()]);
    }
}

it('clears only dependent results and allows the corrected bracket to finish', function (int $players) {
    completeRollbackBracket($this, $players);
    $matches = $this->tournament->matches()->orderBy('suggested_play_order')->get();
    $changed = $matches->first();
    $affectedIds = [$changed->id];
    foreach ($matches->skip(1) as $match) {
        if (in_array($match->player1_prereq_match_id, $affectedIds) || in_array($match->player2_prereq_match_id, $affectedIds)) {
            $affectedIds[] = $match->id;
        }
    }
    $unrelated = $matches->whereNotIn('id', $affectedIds)->mapWithKeys(fn ($match) => [
        $match->id => [$match->getAttributes(), $match->matchScores()->get()->toArray()],
    ]);
    $newWinner = $changed->player2_id;
    $this->service->updateMatchScores($changed->id, '1-3', $newWinner);

    expect($changed->fresh()->winner_id)->toBe($newWinner)
        ->and($changed->fresh()->state)->toBe(TournamentMatchStateEnum::COMPLETE)
        ->and($this->tournament->players()->whereNotNull('final_rank')->count())->toBe(0);

    foreach (array_slice($affectedIds, 1) as $id) {
        $match = $this->tournament->matches()->findOrFail($id);
        expect($match->winner_id)->toBeNull()
            ->and($match->loser_id)->toBeNull()
            ->and($match->player1_score)->toBeNull()
            ->and($match->player2_score)->toBeNull()
            ->and($match->completed_at)->toBeNull()
            ->and($match->matchScores()->count())->toBe(0)
            ->and($match->state)->toBe($match->player1_id && $match->player2_id
                ? TournamentMatchStateEnum::OPEN : TournamentMatchStateEnum::PENDING);
        if ($match->player1_prereq_match_id === $changed->id) {
            expect($match->player1_id)->toBe($newWinner);
        }
        if ($match->player2_prereq_match_id === $changed->id) {
            expect($match->player2_id)->toBe($newWinner);
        }
    }
    foreach ($unrelated as $id => [$attributes, $scores]) {
        $match = $this->tournament->matches()->findOrFail($id);
        expect($match->getAttributes())->toBe($attributes)
            ->and($match->matchScores()->get()->toArray())->toBe($scores);
    }
    foreach (array_slice($affectedIds, 1) as $id) {
        $match = $this->tournament->matches()->findOrFail($id);
        $this->service->updateMatchScores($id, '3-1', $match->player1_id);
    }
    expect($this->tournament->matches()->whereNull('winner_id')->count())->toBe(0)
        ->and($this->tournament->players()->where('final_rank', 1)->count())->toBe(1);
})->with([5, 8]);

it('preserves later results when only scores change', function () {
    completeRollbackBracket($this, 8);
    $matches = $this->tournament->matches()->orderBy('suggested_play_order')->get();
    $before = $matches->skip(1)->map(fn ($match) => $match->getAttributes())->all();
    $match = $matches->first();
    $this->service->updateMatchScores($match->id, '4-2', $match->winner_id);
    expect($matches->skip(1)->map(fn ($match) => $match->fresh()->getAttributes())->all())->toBe($before);
});

it('recalculates final ranks when the final winner changes', function () {
    completeRollbackBracket($this, 4);
    $final = $this->tournament->matches()->orderByDesc('round')->first();
    $this->service->updateMatchScores($final->id, '1-3', $final->player2_id);
    expect($this->tournament->players()->find($final->player2_id)->final_rank)->toBe(1)
        ->and($this->tournament->players()->find($final->player1_id)->final_rank)->toBe(2);
});

it('rolls back an invalid score edit without losing existing results', function () {
    completeRollbackBracket($this, 4);
    $match = $this->tournament->matches()->first();
    $scores = $match->matchScores()->get()->toArray();
    expect(fn () => $this->service->updateMatchScores($match->id, 'invalid', $match->player2_id))
        ->toThrow(InvalidArgumentException::class);
    expect($match->fresh()->winner_id)->toBe($match->winner_id)
        ->and($match->matchScores()->get()->toArray())->toBe($scores);
});
