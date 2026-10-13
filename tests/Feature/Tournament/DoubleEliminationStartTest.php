<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\Bracket\Contracts\ModeResolver;
use App\Services\TournamentService;

it('validates double elimination checked-in counts before starting', function (bool $split, int $count, bool $eligible) {
    $this->actingAs(User::factory()->create());
    $tournament = Tournament::create([
        'name' => 'DE eligibility', 'open_id' => 'DESTART',
        'mode_type' => 'double_elimination', 'status' => 'pending', 'split_participant' => $split,
    ]);
    // Extra registered players must not satisfy the checked-in minimum.
    foreach (range(1, $count + 3) as $index) {
        $tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => $index <= $count]);
    }
    expect(app(ModeResolver::class)->resolve('double_elimination')->canStart($tournament))->toBe($eligible)
        ->and($tournament->fresh()->status->value)->toBe('pending')
        ->and($tournament->matches()->count())->toBe(0);

    if (!$eligible) {
        $message = $split
            ? 'Split-participant double elimination requires at least 3 checked-in players.'
            : 'Double elimination requires at least 2 checked-in players.';
        $this->post('/tournaments/DESTART/start')->assertSessionHasErrors(['players' => $message]);
        $this->postJson('/api/v1/tournaments/DESTART/actions/start')
            ->assertUnprocessable()->assertJsonValidationErrors('players')
            ->assertJsonPath('errors.players.0', $message);
        expect($tournament->fresh()->status->value)->toBe('pending')
            ->and($tournament->matches()->count())->toBe(0);
        return;
    }

    app(TournamentService::class)->startTournament($tournament->open_id);
    expect($tournament->fresh()->status->value)->toBe('started')
        ->and($tournament->matches()->count())->toBeGreaterThan(0)
        ->and($tournament->matches()->whereNotNull('player1_id')->whereNotNull('player2_id')->exists())->toBeTrue();
})->with([
    'standard empty' => [false, 0, false],
    'standard one' => [false, 1, false],
    'standard minimum' => [false, 2, true],
    'standard uneven' => [false, 5, true],
    'split empty' => [true, 0, false],
    'split one' => [true, 1, false],
    'split two' => [true, 2, false],
    'split minimum' => [true, 3, true],
    'split even' => [true, 4, true],
    'split uneven' => [true, 5, true],
]);
