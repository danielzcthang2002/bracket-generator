<?php

use App\Bracket\Models\DtTournament;
use App\Models\User;
use App\Bracket\Contracts\DtModeResolver;

it('validates Swiss starts without restricting the configured round count', function (int $count, int $rounds, bool $api) {
    $this->actingAs(User::factory()->create());
    $tournament = DtTournament::create([
        'name' => 'Swiss eligibility', 'open_id' => 'SWISSSTART',
        'mode_type' => 'swiss', 'status' => 'pending', 'swiss_rounds' => $rounds,
    ]);
    foreach (range(1, $count + 3) as $index) {
        $tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => $index <= $count]);
    }
    $eligible = $count >= 2;
    expect(app(DtModeResolver::class)->resolve('swiss')->canStart($tournament))->toBe($eligible)
        ->and($tournament->fresh()->status->value)->toBe('pending')
        ->and($tournament->matches()->count())->toBe(0);

    $response = $api
        ? $this->postJson('/api/v1/tournaments/SWISSSTART/actions/start')
        : $this->post('/tournaments/SWISSSTART/start');

    if (!$eligible) {
        $message = 'Swiss requires at least 2 checked-in players.';
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
    $matches = $tournament->matches()->get();
    expect($tournament->fresh()->status->value)->toBe('started')
        ->and($tournament->fresh()->swiss_rounds)->toBe($rounds)
        ->and($matches->pluck('round')->unique()->all())->toBe([1])
        ->and($matches->whereNotNull('player2_id'))->toHaveCount(intdiv($count, 2))
        ->and($matches->whereNull('player2_id'))->toHaveCount($count % 2);
    $actualPlayers = $matches->flatMap(fn ($match) => [$match->player1_id, $match->player2_id])->filter()->sort()->values()->all();
    expect($actualPlayers)->toBe($tournament->players()->checkedIn()->orderBy('id')->pluck('id')->all());
    if ($count % 2 === 1) {
        $bye = $matches->firstWhere('player2_id', null);
        expect($bye->winner_id)->toBe($bye->player1_id)
            ->and($bye->state->value)->toBe('complete');
    }
})->with([
    'empty' => [0, 3],
    'one checked in' => [1, 3],
    'minimum' => [2, 1],
    'minimum with many rounds' => [2, 10],
    'odd count' => [3, 3],
    'larger odd count' => [5, 5],
])->with(['web' => false, 'API' => true]);
