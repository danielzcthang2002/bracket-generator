<?php

use App\Bracket\Models\DtTournament;
use App\Models\User;
use App\Bracket\Services\DtTournamentMatchService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('always creates pending tournaments', function () {
    $this->post('/tournaments', ['name' => 'Cup', 'mode_type' => 'single_elimination', 'status' => 'ended'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(DtTournament::first()->status->value)->toBe('pending');
});

it('locks mode and check-in after starting while allowing name edits', function (string $status) {
    $tournament = DtTournament::create(['name' => 'Cup', 'open_id' => 'LOCKED', 'mode_type' => 'single_elimination', 'status' => $status]);
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
    $tournament = DtTournament::create(['name' => 'Cup', 'open_id' => 'PENDING', 'mode_type' => 'single_elimination', 'status' => 'pending']);
    $player = $tournament->players()->create(['name' => 'Alice', 'checked_in' => false]);
    $this->put('/tournaments/PENDING', ['name' => 'Cup', 'mode_type' => 'double_elimination', 'status' => 'ended'])
        ->assertSessionHasNoErrors();
    expect($tournament->fresh()->status->value)->toBe('pending');
    expect($tournament->fresh()->mode_type->value)->toBe('double_elimination');
    $this->put('/tournaments/PENDING/players/'.$player->id, ['name' => 'Alice', 'checked_in' => true])
        ->assertSessionHasNoErrors();
    expect($player->fresh()->checked_in)->toBeTrue();
});

it('swaps player seeds from the bracket preview', function () {
    $tournament = DtTournament::create(['name' => 'Cup', 'open_id' => 'RESEED', 'mode_type' => 'single_elimination', 'status' => 'pending']);
    $firstPlayer = $tournament->players()->create(['name' => 'Alice', 'seed' => 1, 'checked_in' => true]);
    $secondPlayer = $tournament->players()->create(['name' => 'Bob', 'seed' => 2, 'checked_in' => true]);

    $this->put('/tournaments/RESEED/players/swap-seeds', [
        'first_player_id' => $firstPlayer->id,
        'second_player_id' => $secondPlayer->id,
    ])->assertSessionHasNoErrors()->assertRedirect('/tournaments/RESEED');

    expect($firstPlayer->fresh()->seed)->toBe(2)
        ->and($secondPlayer->fresh()->seed)->toBe(1);
});

it('locks the check-in requirement after starting through the web and API', function (string $status, bool $required) {
    $tournament = DtTournament::create([
        'name' => 'Cup', 'open_id' => 'CHECKINLOCK', 'mode_type' => 'single_elimination',
        'status' => $status, 'require_check_in' => $required,
    ]);
    $payload = ['name' => 'Renamed', 'mode_type' => 'single_elimination', 'require_check_in' => !$required];

    $this->put('/tournaments/CHECKINLOCK', $payload)->assertSessionHasErrors('require_check_in');
    $this->putJson('/api/v1/tournaments/CHECKINLOCK', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('require_check_in');
    expect($tournament->fresh()->require_check_in)->toBe($required)
        ->and($tournament->fresh()->name)->toBe('Cup');

    $payload['require_check_in'] = $required;
    $this->put('/tournaments/CHECKINLOCK', $payload)->assertSessionHasNoErrors();
    expect($tournament->fresh()->name)->toBe('Renamed')
        ->and($tournament->fresh()->require_check_in)->toBe($required);
})->with(['started', 'ended'])->with([true, false]);

it('allows changing the check-in requirement before starting', function (bool $required) {
    $tournament = DtTournament::create([
        'name' => 'Cup', 'open_id' => 'CHECKINEDIT', 'mode_type' => 'single_elimination',
        'status' => 'pending', 'require_check_in' => $required,
    ]);
    $this->put('/tournaments/CHECKINEDIT', [
        'name' => 'Cup', 'mode_type' => 'single_elimination', 'require_check_in' => !$required,
    ])->assertSessionHasNoErrors();
    expect($tournament->fresh()->require_check_in)->toBe(!$required);
})->with([true, false]);

it('rejects all score updates after ending', function () {
    $tournament = DtTournament::create(['name' => 'Cup', 'open_id' => 'ENDED', 'mode_type' => 'single_elimination', 'status' => 'ended']);
    $match = $tournament->matches()->create(['round' => 1, 'state' => 'pending', 'suggested_play_order' => 1]);
    $service = app(DtTournamentMatchService::class);
    expect(fn () => $service->updateMatchScores($match->id, '1-0', null))->toThrow(ValidationException::class);
    expect(fn () => $service->updateFfaMatchScores($match->id, []))->toThrow(ValidationException::class);
});
