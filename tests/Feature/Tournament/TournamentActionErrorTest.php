<?php

use App\Bracket\Models\DtTournament;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('returns a validation error when ending with incomplete matches', function () {
    $tournament = DtTournament::create([
        'name' => 'Unfinished Cup', 'open_id' => 'UNFINISHED',
        'mode_type' => 'single_elimination', 'status' => 'started',
    ]);
    $tournament->matches()->create([
        'round' => 1, 'suggested_play_order' => 1, 'state' => 'open',
    ]);

    $message = 'All matches need to be completed before ending the tournament.';
    $this->post('/tournaments/UNFINISHED/end')->assertSessionHasErrors(['tournament' => $message]);
    $this->postJson('/api/v1/tournaments/UNFINISHED/actions/end')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tournament')
        ->assertJsonPath('errors.tournament.0', $message);

    expect($tournament->fresh()->status->value)->toBe('started');
});

it('returns a validation error when ending without matches', function () {
    $tournament = DtTournament::create([
        'name' => 'Empty Cup', 'open_id' => 'EMPTYEND',
        'mode_type' => 'single_elimination', 'status' => 'started',
    ]);

    $this->post('/tournaments/EMPTYEND/end')->assertSessionHasErrors('tournament');
    expect($tournament->fresh()->status->value)->toBe('started');
});
