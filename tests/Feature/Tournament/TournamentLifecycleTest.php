<?php

use App\Bracket\Models\DtTournament;
use App\Models\User;
use App\Bracket\Services\DtTournamentMatchService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('rolls back one status step while resetting only started brackets', function (string $mode, string $status, bool $api) {
    $tournament = DtTournament::create([
        'name' => 'Rollback Cup', 'open_id' => 'ROLLBACK', 'mode_type' => $mode,
        'status' => $status, 'swiss_rounds' => 3, 'ffa_heat_size' => 4, 'ffa_advance_count' => 2,
    ]);
    $player = $tournament->players()->create(['name' => 'Alice', 'seed' => 1, 'checked_in' => true, 'final_rank' => 1]);
    $other = $tournament->players()->create(['name' => 'Bob', 'seed' => 2, 'checked_in' => true, 'final_rank' => 2]);
    $match = $tournament->matches()->create([
        'round' => 1, 'suggested_play_order' => 1, 'state' => 'complete',
        'player1_id' => $player->id, 'player2_id' => $other->id, 'winner_id' => $player->id,
    ]);
    $score = $match->matchScores()->create(['player_id' => $player->id, 'set' => 1, 'score' => 3]);
    $participant = $match->participants()->create(['player_id' => $player->id, 'position' => 1, 'score' => 3, 'rank' => 1, 'is_winner' => true]);
    $dependentMatch = $tournament->matches()->create([
        'round' => 2, 'suggested_play_order' => 2, 'state' => 'complete',
        'player1_id' => $player->id, 'player1_prereq_match_id' => $match->id, 'winner_id' => $player->id,
    ]);
    $unrelated = DtTournament::create(['name' => 'Other', 'open_id' => 'OTHER', 'mode_type' => $mode, 'status' => 'started']);
    $unrelatedMatch = $unrelated->matches()->create(['round' => 1, 'suggested_play_order' => 1]);
    $url = $api ? '/api/v1/tournaments/ROLLBACK/actions/rollback' : '/tournaments/ROLLBACK/rollback';

    if ($api) {
        $this->postJson($url, ['status' => $status])->assertOk()
            ->assertJsonPath('data.status', $status === 'started' ? 'pending' : 'started');
    } else {
        $this->post($url, ['status' => $status])->assertSessionHasNoErrors()->assertRedirect('/tournaments/ROLLBACK');
    }

    expect($tournament->fresh()->status->value)->toBe($status === 'started' ? 'pending' : 'started')
        ->and($player->fresh()->seed)->toBe(1)
        ->and($player->fresh()->checked_in)->toBeTrue()
        ->and($tournament->players()->count())->toBe(2)
        ->and($unrelatedMatch->fresh())->not->toBeNull();

    if ($status === 'started') {
        expect($tournament->matches()->count())->toBe(0)
            ->and($score->fresh())->toBeNull()
            ->and($participant->fresh())->toBeNull()
            ->and($player->fresh()->final_rank)->toBeNull();
        app(\App\Bracket\Services\DtTournamentService::class)->startTournament('ROLLBACK');
        expect($tournament->fresh()->status->value)->toBe('started')
            ->and($tournament->matches()->exists())->toBeTrue();
    } else {
        expect($match->fresh()->winner_id)->toBe($player->id)
            ->and($dependentMatch->fresh()->player1_prereq_match_id)->toBe($match->id)
            ->and($score->fresh()->score)->toBe('3.00')
            ->and($participant->fresh()->is_winner)->toBeTrue()
            ->and($player->fresh()->final_rank)->toBe(1);
        // A repeated reopen must not turn into a destructive bracket reset.
        $this->postJson('/api/v1/tournaments/ROLLBACK/actions/rollback', ['status' => 'ended'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        expect($match->fresh())->not->toBeNull();
        app(DtTournamentMatchService::class)->updateMatchScores($match->id, '3-0', $player->id);
    }
})->with(['single_elimination', 'double_elimination', 'round_robin', 'swiss', 'free_for_all'])
    ->with(['started', 'ended'])->with([false, true]);

it('rejects invalid rollback requests without deleting matches', function (string $status, ?string $expected) {
    $tournament = DtTournament::create(['name' => 'Cup', 'open_id' => 'INVALID', 'mode_type' => 'swiss', 'status' => $status]);
    $match = $tournament->matches()->create(['round' => 1, 'suggested_play_order' => 1]);
    $this->postJson('/api/v1/tournaments/INVALID/actions/rollback', ['status' => $expected])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($tournament->fresh()->status->value)->toBe($status)
        ->and($match->fresh())->not->toBeNull();
})->with([['pending', 'started'], ['started', 'ended'], ['ended', 'started'], ['started', 'pending'], ['started', null]]);

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
