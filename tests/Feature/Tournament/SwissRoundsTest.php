<?php

use App\Bracket\Models\DtTournament;
use App\Models\User;
use App\Bracket\Services\DtTournamentService;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->tournament = DtTournament::create([
        'name' => 'Swiss Cup', 'open_id' => 'SWISSROUNDS', 'mode_type' => 'swiss',
        'status' => 'pending', 'swiss_rounds' => 1,
        'points_per_match_win' => 1, 'points_per_match_tie' => 0.5,
        'points_per_set_win' => 0, 'points_per_set_tie' => 0, 'points_per_bye' => 1,
    ]);
    for ($index = 1; $index <= 4; $index++) {
        $this->tournament->players()->create(['name' => 'Player '.$index, 'checked_in' => true]);
    }
});

it('saves pending round counts without persisting the preview', function () {
    $this->put('/tournaments/SWISSROUNDS/swiss-rounds', ['swiss_rounds' => 3])->assertSessionHasNoErrors();
    expect($this->tournament->fresh()->swiss_rounds)->toBe(3);
    expect($this->tournament->matches()->count())->toBe(0);
});

it('generates the next round on an update and preserves completed matches', function (string $endpoint) {
    app(DtTournamentService::class)->startTournament('SWISSROUNDS');
    $firstRound = $this->tournament->matches()->get();
    foreach ($firstRound as $match) {
        $match->update(['winner_id' => $match->player1_id, 'state' => 'complete']);
    }
    $payload = ['swiss_rounds' => 3];
    if ($endpoint === '') {
        $payload += ['name' => 'Swiss Cup', 'mode_type' => 'swiss'];
    }
    $this->put('/tournaments/SWISSROUNDS'.$endpoint, $payload)->assertSessionHasNoErrors();
    expect($this->tournament->fresh()->swiss_rounds)->toBe(3);
    expect($this->tournament->matches()->where('round', 2)->count())->toBe(2);
    foreach ($firstRound as $match) {
        expect($match->fresh()->winner_id)->toBe($match->player1_id);
    }
    $this->put('/tournaments/SWISSROUNDS/swiss-rounds', ['swiss_rounds' => 4])->assertSessionHasNoErrors();
    expect($this->tournament->matches()->count())->toBe(4);
    $this->put('/tournaments/SWISSROUNDS/swiss-rounds', ['swiss_rounds' => 1])->assertSessionHasErrors('swiss_rounds');
    expect($this->tournament->fresh()->swiss_rounds)->toBe(4);
})->with(['detail' => '/swiss-rounds', 'edit' => '']);

it('rejects invalid rounds and changes to ended or non-Swiss tournaments', function () {
    $this->put('/tournaments/SWISSROUNDS/swiss-rounds', ['swiss_rounds' => 0])->assertSessionHasErrors('swiss_rounds');
    $this->put('/tournaments/SWISSROUNDS/swiss-rounds', ['swiss_rounds' => 1.5])->assertSessionHasErrors('swiss_rounds');
    $this->tournament->update(['status' => 'ended']);
    $this->put('/tournaments/SWISSROUNDS/swiss-rounds', ['swiss_rounds' => 3])->assertSessionHasErrors('swiss_rounds');
    $this->tournament->update(['status' => 'pending', 'mode_type' => 'single_elimination']);
    $this->put('/tournaments/SWISSROUNDS/swiss-rounds', ['swiss_rounds' => 3])->assertSessionHasErrors('swiss_rounds');
});

it('persists round counts through the API update service and generates matches', function () {
    app(DtTournamentService::class)->startTournament('SWISSROUNDS');
    foreach ($this->tournament->matches()->get() as $match) {
        $match->update(['winner_id' => $match->player1_id, 'state' => 'complete']);
    }
    app(DtTournamentService::class)->updateTournament('SWISSROUNDS', [
        'mode_type' => 'swiss', 'swiss_rounds' => 3,
        'points_per_match_win' => 1, 'points_per_match_tie' => 0.5,
        'points_per_set_win' => 0, 'points_per_set_tie' => 0, 'points_per_bye' => 1,
    ]);
    expect($this->tournament->fresh()->swiss_rounds)->toBe(3);
    expect($this->tournament->matches()->where('round', 2)->count())->toBe(2);
});
