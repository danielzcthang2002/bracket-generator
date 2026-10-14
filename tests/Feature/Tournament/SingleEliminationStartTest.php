<?php

use App\Bracket\Models\DtTournament;
use App\Models\User;
use App\Bracket\Contracts\DtModeResolver;
use App\Bracket\Services\DtTournamentService;

beforeEach(function () {
    $this->tournament = DtTournament::create([
        'name' => 'Start eligibility', 'open_id' => 'STARTCHECK',
        'mode_type' => 'single_elimination', 'status' => 'pending',
    ]);
});

it('checks the actual checked-in roster without modifying the tournament', function (int $checkedIn, bool $expected) {
    foreach (range(1, 6) as $index) {
        $this->tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => $index <= $checkedIn]);
    }
    $mode = app(DtModeResolver::class)->resolve('single_elimination');
    expect($mode->canStart($this->tournament))->toBe($expected)
        ->and($this->tournament->fresh()->status->value)->toBe('pending')
        ->and($this->tournament->matches()->count())->toBe(0);
})->with([[0, false], [1, false], [2, true], [3, true], [5, true]]);

it('rejects an invalid start through the web and API without changing state', function (int $checkedIn) {
    $this->actingAs(User::factory()->create());
    foreach (range(1, 3) as $index) {
        $this->tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => $index <= $checkedIn]);
    }
    $this->post('/tournaments/STARTCHECK/start')->assertSessionHasErrors('players');
    $this->postJson('/api/v1/tournaments/STARTCHECK/actions/start')->assertUnprocessable()->assertJsonValidationErrors('players');
    expect($this->tournament->fresh()->status->value)->toBe('pending')
        ->and($this->tournament->matches()->count())->toBe(0);
})->with([0, 1]);

it('starts with two or more checked-in players including uneven brackets', function (int $count) {
    foreach (range(1, $count) as $index) {
        $this->tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => true]);
    }
    app(DtTournamentService::class)->startTournament('STARTCHECK');
    expect($this->tournament->fresh()->status->value)->toBe('started')
        ->and($this->tournament->matches()->count())->toBe($count - 1);
})->with([2, 3, 5]);
