<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\Bracket\Contracts\ModeResolver;

it('requires two checked-in players to start round robin through the web and API', function (int $count, bool $api) {
    $this->actingAs(User::factory()->create());
    $tournament = Tournament::create([
        'name' => 'Round robin eligibility', 'open_id' => 'RRSTART',
        'mode_type' => 'round_robin', 'status' => 'pending', 'head_to_head_count' => 1,
    ]);
    foreach (range(1, $count + 3) as $index) {
        $tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => $index <= $count]);
    }
    $eligible = $count >= 2;
    expect(app(ModeResolver::class)->resolve('round_robin')->canStart($tournament))->toBe($eligible)
        ->and($tournament->fresh()->status->value)->toBe('pending')
        ->and($tournament->matches()->count())->toBe(0);

    $response = $api
        ? $this->postJson('/api/v1/tournaments/RRSTART/actions/start')
        : $this->post('/tournaments/RRSTART/start');

    if (!$eligible) {
        $message = 'Round robin requires at least 2 checked-in players.';
        if ($api) {
            $response->assertUnprocessable()->assertJsonValidationErrors('players')
                ->assertJsonPath('errors.players.0', $message);
        } else {
            $response->assertSessionHasErrors(['players' => $message]);
        }
        expect($tournament->fresh()->status->value)->toBe('pending')
            ->and($tournament->matches()->count())->toBe(0);
        return;
    }

    if ($api) {
        $response->assertSuccessful();
    } else {
        $response->assertRedirect()->assertSessionHasNoErrors();
    }
    expect($tournament->fresh()->status->value)->toBe('started')
        ->and($tournament->matches()->whereNotNull('player1_id')->whereNotNull('player2_id')->count())
        ->toBe(intdiv($count * ($count - 1), 2));
})->with([0, 1, 2, 3, 5])->with(['web' => false, 'API' => true]);
