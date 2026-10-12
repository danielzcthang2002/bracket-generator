<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentMatchService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('always creates pending tournaments', function () {
    $this->post('/tournaments', ['name' => 'Cup', 'mode_type' => 'single_elimination', 'status' => 'ended'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(Tournament::first()->status->value)->toBe('pending');
});

it('locks mode and check-in after starting while allowing name edits', function (string $status) {
    $tournament = Tournament::create(['name' => 'Cup', 'open_id' => 'LOCKED', 'mode_type' => 'single_elimination', 'status' => $status]);
    $player = $tournament->players()->create(['name' => 'Alice', 'checked_in' => true]);

    $this->put('/tournaments/LOCKED', ['name' => 'Renamed', 'mode_type' => 'swiss'])
        ->assertSessionHasErrors('mode_type');
    expect($tournament->fresh()->mode_type->value)->toBe('single_elimination');

    $this->put('/tournaments/LOCKED', ['name' => 'Renamed', 'mode_type' => 'single_elimination', 'status' => 'pending'])
        ->assertSessionHasNoErrors();
    expect($tournament->fresh()->status->value)->toBe($status);

    $this->put('/tournaments/LOCKED/players/'.$player->id, ['name' => 'Alice', 'checked_in' => false])
        ->assertSessionHasErrors('checked_in');
    expect($player->fresh()->checked_in)->toBeTrue();
    $this->put('/tournaments/LOCKED/players/'.$player->id, ['name' => 'Bob'])
        ->assertSessionHasNoErrors();
    expect($player->fresh()->name)->toBe('Bob');
    expect($player->fresh()->checked_in)->toBeTrue();
})->with(['started', 'ended']);

it('allows mode and check-in edits before starting but ignores status edits', function () {
    $tournament = Tournament::create(['name' => 'Cup', 'open_id' => 'PENDING', 'mode_type' => 'single_elimination', 'status' => 'pending']);
    $player = $tournament->players()->create(['name' => 'Alice', 'checked_in' => false]);
    $this->put('/tournaments/PENDING', ['name' => 'Cup', 'mode_type' => 'double_elimination', 'status' => 'ended'])
        ->assertSessionHasNoErrors();
    expect($tournament->fresh()->status->value)->toBe('pending');
    expect($tournament->fresh()->mode_type->value)->toBe('double_elimination');
    $this->put('/tournaments/PENDING/players/'.$player->id, ['name' => 'Alice', 'checked_in' => true])
        ->assertSessionHasNoErrors();
    expect($player->fresh()->checked_in)->toBeTrue();
});

it('rejects all score updates after ending', function () {
    $tournament = Tournament::create(['name' => 'Cup', 'open_id' => 'ENDED', 'mode_type' => 'single_elimination', 'status' => 'ended']);
    $match = $tournament->matches()->create(['round' => 1, 'state' => 'pending', 'suggested_play_order' => 1]);
    $service = app(TournamentMatchService::class);
    expect(fn () => $service->updateMatchScores($match->id, '1-0', null))->toThrow(ValidationException::class);
    expect(fn () => $service->updateFfaMatchScores($match->id, []))->toThrow(ValidationException::class);
});
