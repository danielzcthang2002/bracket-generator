<?php

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\TournamentMatchService;

dataset('single elimination cases', [
    '2 players' => [
        'playerCount' => 2,
        'expectedTotalMatchCount' => 1,
        'expectedRounds' => [1 => 1],
        'expectedRoundOnePlayerCount' => 2,
    ],
    '3 players' => [
        'playerCount' => 3,
        'expectedTotalMatchCount' => 2,
        'expectedRounds' => [1 => 1, 2 => 1],
        'expectedRoundOnePlayerCount' => 2,
    ],
    '4 players' => [
        'playerCount' => 4,
        'expectedTotalMatchCount' => 3,
        'expectedRounds' => [1 => 2, 2 => 1],
        'expectedRoundOnePlayerCount' => 4,
    ],
    '5 players' => [
        'playerCount' => 5,
        'expectedTotalMatchCount' => 4,
        'expectedRounds' => [1 => 1, 2 => 2, 3 => 1],
        'expectedRoundOnePlayerCount' => 2,
    ],
    '6 players' => [
        'playerCount' => 6,
        'expectedTotalMatchCount' => 5,
        'expectedRounds' => [1 => 2, 2 => 2, 3 => 1],
        'expectedRoundOnePlayerCount' => 4,
    ],
    '7 players' => [
        'playerCount' => 7,
        'expectedTotalMatchCount' => 6,
        'expectedRounds' => [1 => 3, 2 => 2, 3 => 1],
        'expectedRoundOnePlayerCount' => 6,
    ],
    '8 players' => [
        'playerCount' => 8,
        'expectedTotalMatchCount' => 7,
        'expectedRounds' => [1 => 4, 2 => 2, 3 => 1],
        'expectedRoundOnePlayerCount' => 8,
    ],
    '9 players' => [
        'playerCount' => 9,
        'expectedTotalMatchCount' => 8,
        'expectedRounds' => [1 => 1, 2 => 4, 3 => 2, 4 => 1],
        'expectedRoundOnePlayerCount' => 2,
    ],
    '13 players' => [
        'playerCount' => 13,
        'expectedTotalMatchCount' => 12,
        'expectedRounds' => [1 => 5, 2 => 4, 3 => 2, 4 => 1],
        'expectedRoundOnePlayerCount' => 10,
    ],
    '15 players' => [
        'playerCount' => 15,
        'expectedTotalMatchCount' => 14,
        'expectedRounds' => [1 => 7, 2 => 4, 3 => 2, 4 => 1],
        'expectedRoundOnePlayerCount' => 14,
    ],
    '16 players' => [
        'playerCount' => 16,
        'expectedTotalMatchCount' => 15,
        'expectedRounds' => [1 => 8, 2 => 4, 3 => 2, 4 => 1],
        'expectedRoundOnePlayerCount' => 16,
    ],
    '17 players' => [
        'playerCount' => 17,
        'expectedTotalMatchCount' => 16,
        'expectedRounds' => [1 => 1, 2 => 8, 3 => 4, 4 => 2, 5 => 1],
        'expectedRoundOnePlayerCount' => 2,
    ],
    '18 players' => [
        'playerCount' => 18,
        'expectedTotalMatchCount' => 17,
        'expectedRounds' => [1 => 2, 2 => 8, 3 => 4, 4 => 2, 5 => 1],
        'expectedRoundOnePlayerCount' => 4,
    ],
    '19 players' => [
        'playerCount' => 19,
        'expectedTotalMatchCount' => 18,
        'expectedRounds' => [1 => 3, 2 => 8, 3 => 4, 4 => 2, 5 => 1],
        'expectedRoundOnePlayerCount' => 6,
    ],
    '27 players' => [
        'playerCount' => 27,
        'expectedTotalMatchCount' => 26,
        'expectedRounds' => [1 => 11, 2 => 8, 3 => 4, 4 => 2, 5 => 1],
        'expectedRoundOnePlayerCount' => 22,
    ],
    '32 players' => [
        'playerCount' => 32,
        'expectedTotalMatchCount' => 31,
        'expectedRounds' => [1 => 16, 2 => 8, 3 => 4, 4 => 2, 5 => 1],
        'expectedRoundOnePlayerCount' => 32,
    ],
    '39 players' => [
        'playerCount' => 39,
        'expectedTotalMatchCount' => 38,
        'expectedRounds' => [1 => 7, 2 => 16, 3 => 8, 4 => 4, 5 => 2, 6 => 1],
        'expectedRoundOnePlayerCount' => 14,
    ],
    '45 players' => [
        'playerCount' => 45,
        'expectedTotalMatchCount' => 44,
        'expectedRounds' => [1 => 13, 2 => 16, 3 => 8, 4 => 4, 5 => 2, 6 => 1],
        'expectedRoundOnePlayerCount' => 26,
    ],
    '47 players' => [
        'playerCount' => 47,
        'expectedTotalMatchCount' => 46,
        'expectedRounds' => [1 => 15, 2 => 16, 3 => 8, 4 => 4, 5 => 2, 6 => 1],
        'expectedRoundOnePlayerCount' => 30,
    ],
    '55 players' => [
        'playerCount' => 55,
        'expectedTotalMatchCount' => 54,
        'expectedRounds' => [1 => 23, 2 => 16, 3 => 8, 4 => 4, 5 => 2, 6 => 1],
        'expectedRoundOnePlayerCount' => 46,
    ],
    '64 players' => [
        'playerCount' => 64,
        'expectedTotalMatchCount' => 63,
        'expectedRounds' => [1 => 32, 2 => 16, 3 => 8, 4 => 4, 5 => 2, 6 => 1],
        'expectedRoundOnePlayerCount' => 64,
    ],
]);

it('generates a single elimination bracket', function (
    int $playerCount,
    int $expectedTotalMatchCount,
    array $expectedRounds,
    int $expectedRoundOnePlayerCount,
) {
    $tournament = Tournament::create([
        'name' => 'Single Elimination Test ' . $playerCount,
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => TournamentModeEnum::SINGLE_ELIMINATION,
        'status' => TournamentStatus::OPEN,
    ]);

    collect(range(1, $playerCount))->map(function (int $index) use ($tournament): Player {
        return Player::create([
            'name' => 'Player ' . $index,
            'tournament_id' => $tournament->id,
            'checked_in' => true,
        ]);
    });

    $matches = (new TournamentMatchService())->generateMatches($tournament->id);

    expect($matches)->toHaveCount($expectedTotalMatchCount);

    foreach ($expectedRounds as $round => $expectedCount) {
        expect($matches->where('round', $round))->toHaveCount($expectedCount);
    }

    $roundOneParticipants = $matches
        ->where('round', 1)
        ->flatMap(fn($match) => [$match->player1_id, $match->player2_id])
        ->filter()
        ->count();

    expect($roundOneParticipants)->toBe($expectedRoundOnePlayerCount);
})->with('single elimination cases');