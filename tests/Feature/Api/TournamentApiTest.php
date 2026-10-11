<?php

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Tournament;

it('exposes tournaments through the versioned REST endpoint', function () {
    $tournament = Tournament::query()->create([
        'name' => 'Autumn Open',
        'open_id' => 'AUTUMN01',
        'mode_type' => TournamentModeEnum::SINGLE_ELIMINATION,
        'status' => TournamentStatus::PENDING,
    ]);

    $this->getJson('/api/v1/tournaments')
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('data.0.open_id', $tournament->open_id)
        ->assertJsonPath('data.0.mode_type', 'single_elimination')
        ->assertJsonMissingPath('status');
});

it('creates a tournament with the normalized payload and response format', function () {
    $this->postJson('/api/v1/tournaments', [
        'name' => 'Winter Cup',
        'mode_type' => 'single_elimination',
        'require_check_in' => false,
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'Tournament created successfully.')
        ->assertJsonPath('data.name', 'Winter Cup')
        ->assertJsonPath('data.mode_type', 'single_elimination')
        ->assertJsonStructure(['data' => ['id', 'open_id', 'name', 'mode_type', 'status']]);
});

it('uses one check-in endpoint for both participant states', function () {
    $tournament = Tournament::query()->create([
        'name' => 'Check-in Cup',
        'open_id' => 'CHECKIN01',
        'mode_type' => TournamentModeEnum::SINGLE_ELIMINATION,
        'status' => TournamentStatus::PENDING,
    ]);
    $player = $tournament->players()->create(['name' => 'Player One', 'seed' => 1, 'checked_in' => false]);

    $this->patchJson("/api/v1/tournaments/{$tournament->open_id}/participants/{$player->id}/check-in", [
        'checked_in' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.checked_in', true);

    expect($player->fresh()->checked_in)->toBeTrue();
});

it('does not expose the legacy json-suffixed routes', function () {
    $this->getJson('/api/v1/tournaments.json')->assertNotFound();
});

it('prevents bulk participant deletion after a tournament has started', function () {
    $tournament = Tournament::query()->create([
        'name' => 'Started Cup',
        'open_id' => 'STARTED01',
        'mode_type' => TournamentModeEnum::SINGLE_ELIMINATION,
        'status' => TournamentStatus::STARTED,
    ]);
    $players = collect([
        $tournament->players()->create(['name' => 'Player One', 'seed' => 1, 'checked_in' => true]),
        $tournament->players()->create(['name' => 'Player Two', 'seed' => 2, 'checked_in' => true]),
        $tournament->players()->create(['name' => 'Player Three', 'seed' => 3, 'checked_in' => true]),
    ]);

    $this->deleteJson("/api/v1/tournaments/{$tournament->open_id}/participants", [
        'player_ids' => $players->take(2)->pluck('id')->all(),
    ])
        ->assertConflict()
        ->assertJsonPath('message', 'Players cannot be deleted after a tournament has started.');

    expect($tournament->players()->count())->toBe(3);
});
