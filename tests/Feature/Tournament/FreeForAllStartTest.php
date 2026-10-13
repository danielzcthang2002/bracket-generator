<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\Bracket\Contracts\ModeResolver;

it('requires two checked-in players for FFA regardless of heat size', function (int $count, int $heatSize, bool $api) {
    $this->actingAs(User::factory()->create());
    $tournament = Tournament::create([
        'name' => 'FFA eligibility', 'open_id' => 'FFASTART',
        'mode_type' => 'free_for_all', 'status' => 'pending',
        'ffa_heat_size' => $heatSize, 'ffa_advance_count' => max(1, intdiv($heatSize, 2)),
    ]);
    foreach (range(1, $count + 3) as $index) {
        $tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => $index <= $count]);
    }
    $eligible = $count >= 2;
    expect(app(ModeResolver::class)->resolve('free_for_all')->canStart($tournament))->toBe($eligible)
        ->and($tournament->fresh()->status->value)->toBe('pending')
        ->and($tournament->matches()->count())->toBe(0);

    $response = $api
        ? $this->postJson('/api/v1/tournaments/FFASTART/actions/start')
        : $this->post('/tournaments/FFASTART/start');

    if (!$eligible) {
        $message = 'Free-for-all requires at least 2 checked-in players.';
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
    $matches = $tournament->matches()->with('participants')->get();
    expect($tournament->fresh()->status->value)->toBe('started')
        ->and($tournament->fresh()->ffa_heat_size)->toBe($heatSize)
        ->and($matches)->toHaveCount((int) ceil($count / $heatSize))
        ->and($matches->pluck('round')->unique()->all())->toBe([1]);
    $actualPlayers = $matches->flatMap(fn ($match) => $match->participants->pluck('player_id'))->sort()->values()->all();
    expect($actualPlayers)->toBe($tournament->players()->checkedIn()->orderBy('id')->pluck('id')->all());
    foreach ($matches as $match) {
        expect($match->state->value)->toBe($match->participants->count() === 1 ? 'complete' : 'open');
    }
})->with([
    'empty' => [0, 4],
    'one checked in' => [1, 4],
    'minimum with a large heat' => [2, 8],
    'single undersized final' => [3, 8],
    'full heat' => [4, 4],
    'multiple uneven heats' => [9, 4],
    'bye heat' => [3, 2],
])->with(['web' => false, 'API' => true]);
