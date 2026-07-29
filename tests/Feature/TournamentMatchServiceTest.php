<?php

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\TournamentMatchService;

it('marks a match as a tie when tie is submitted from the score modal', function () {
    $tournament = Tournament::create([
        'name' => 'Tie Test Tournament',
        'open_id' => 'TIE12345',
        'mode_type' => TournamentModeEnum::SINGLE_ELIMINATION,
        'status' => TournamentStatus::OPEN,
    ]);

    $playerOne = Player::create([
        'name' => 'Player One',
        'tournament_id' => $tournament->id,
        'checked_in' => true,
    ]);

    $playerTwo = Player::create([
        'name' => 'Player Two',
        'tournament_id' => $tournament->id,
        'checked_in' => true,
    ]);

    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'state' => 'pending',
        'player1_id' => $playerOne->id,
        'player2_id' => $playerTwo->id,
        'round' => 1,
        'suggested_play_order' => 1,
    ]);

    $service = new TournamentMatchService();

    $updatedMatch = $service->updateMatchScores($match->id, '10-10,8-8', 'tie');

    expect($updatedMatch->fresh())
        ->is_tie->toBeTrue()
        ->and($updatedMatch->fresh()->winner_id)->toBeNull()
        ->and($updatedMatch->fresh()->loser_id)->toBeNull()
        ->and($updatedMatch->fresh()->state->value)->toBe('complete');
});
