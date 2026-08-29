<?php

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\TournamentMatchService;

dataset('double elimination split participant cases', [
    '3 players' => [
        'playerCount' => 3,
        'expectedTotalMatchCount' => 3,
        'expectedWinnersRounds' => [1 => 1, 2 => 1,],
        'expectedLosersRounds' => [-1 => 1,],
        'expectedWinnersRoundOnePlayerCount' => 2,
        'expectedLosersRoundOnePlayerCount' => 1,
    ],
    '4 players' => [
        'playerCount' => 4,
        'expectedTotalMatchCount' => 4,
        'expectedWinnersRounds' => [1 => 1, 2 => 1,],
        'expectedLosersRounds' => [-1 => 1, 2 => 1],
        'expectedWinnersRoundOnePlayerCount' => 2,
        'expectedLosersRoundOnePlayerCount' => 2,
    ],
    '5 players' => [
        'playerCount' => 5,
        'expectedTotalMatchCount' => 7,
        'expectedWinnersRounds' => [1 => 2, 2 => 1, 3 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 1, -3 => 1],
        'expectedWinnersRoundOnePlayerCount' => 4,
        'expectedLosersRoundOnePlayerCount' => 1,
    ],
    '6 players' => [
        'playerCount' => 6,
        'expectedTotalMatchCount' => 8,
        'expectedWinnersRounds' => [1 => 2, 2 => 1, 3 => 1],
        'expectedLosersRounds' => [-1 => 2, -2 => 1, -3 => 1],
        'expectedWinnersRoundOnePlayerCount' => 4,
        'expectedLosersRoundOnePlayerCount' => 2,
    ],
    '7 players' => [
        'playerCount' => 7,
        'expectedTotalMatchCount' => 9,
        'expectedWinnersRounds' => [1 => 2, 2 => 1, 3 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 2, -3 => 1, -4 => 1],
        'expectedWinnersRoundOnePlayerCount' => 4,
        'expectedLosersRoundOnePlayerCount' => 2,
    ],
    '8 players' => [
        'playerCount' => 8,
        'expectedTotalMatchCount' => 10,
        'expectedWinnersRounds' => [1 => 2, 2 => 1, 3 => 1],
        'expectedLosersRounds' => [-1 => 2, -2 => 2, -3 => 1, -4 => 1],
        'expectedWinnersRoundOnePlayerCount' => 4,
        'expectedLosersRoundOnePlayerCount' => 4,
    ],
    '9 players' => [
        'playerCount' => 9,
        'expectedTotalMatchCount' => 15,
        'expectedWinnersRounds' => [1 => 4, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 2, -3 => 2, -4 => 1, -5 => 1],
        'expectedWinnersRoundOnePlayerCount' => 8,
        'expectedLosersRoundOnePlayerCount' => 1,
    ],
    '10 players' => [
        'playerCount' => 10,
        'expectedTotalMatchCount' => 16,
        'expectedWinnersRounds' => [1 => 4, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 2, -2 => 2, -3 => 2, -4 => 1, -5 => 1],
        'expectedWinnersRoundOnePlayerCount' => 8,
        'expectedLosersRoundOnePlayerCount' => 2,
    ],
    '11 players' => [
        'playerCount' => 11,
        'expectedTotalMatchCount' => 17,
        'expectedWinnersRounds' => [1 => 4, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 3, -2 => 2, -3 => 2, -4 => 1, -5 => 1],
        'expectedWinnersRoundOnePlayerCount' => 8,
        'expectedLosersRoundOnePlayerCount' => 3,
    ],
    '12 players' => [
        'playerCount' => 12,
        'expectedTotalMatchCount' => 18,
        'expectedWinnersRounds' => [1 => 4, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 4, -2 => 2, -3 => 2, -4 => 1, -5 => 1],
        'expectedWinnersRoundOnePlayerCount' => 8,
        'expectedLosersRoundOnePlayerCount' => 4,
    ],
    '13 players' => [
        'playerCount' => 13,
        'expectedTotalMatchCount' => 19,
        'expectedWinnersRounds' => [1 => 4, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 4, -3 => 2, -4 => 2, -5 => 1, -6 => 1],
        'expectedWinnersRoundOnePlayerCount' => 8,
        'expectedLosersRoundOnePlayerCount' => 2,
    ],
    '14 players' => [
        'playerCount' => 14,
        'expectedTotalMatchCount' => 20,
        'expectedWinnersRounds' => [1 => 4, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 2, -2 => 4, -3 => 2, -4 => 2, -5 => 1, -6 => 1],
        'expectedWinnersRoundOnePlayerCount' => 8,
        'expectedLosersRoundOnePlayerCount' => 4,
    ],
    '15 players' => [
        'playerCount' => 15,
        'expectedTotalMatchCount' => 21,
        'expectedWinnersRounds' => [1 => 4, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 3, -2 => 4, -3 => 2, -4 => 2, -5 => 1, -6 => 1],
        'expectedWinnersRoundOnePlayerCount' => 8,
        'expectedLosersRoundOnePlayerCount' => 6,
    ],
    '17 players' => [
        'playerCount' => 17,
        'expectedTotalMatchCount' => 31,
        'expectedWinnersRounds' => [1 => 8, 2 => 4, 3 => 2, 4 => 1, 5 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 4, -3 => 4, -4 => 2, -5 => 2, -6 => 1, -7 => 1],
        'expectedWinnersRoundOnePlayerCount' => 16,
        'expectedLosersRoundOnePlayerCount' => 1,
    ],
    '23 players' => [
        'playerCount' => 23,
        'expectedTotalMatchCount' => 37,
        'expectedWinnersRounds' => [1 => 8, 2 => 4, 3 => 2, 4 => 1, 5 => 1],
        'expectedLosersRounds' => [-1 => 7, -2 => 4, -3 => 4, -4 => 2, -5 => 2, -6 => 1, -7 => 1],
        'expectedWinnersRoundOnePlayerCount' => 16,
        'expectedLosersRoundOnePlayerCount' => 7,
    ],
]);

it('generates a split participant double elimination bracket', function (
    int $playerCount,
    int $expectedTotalMatchCount,
    array $expectedWinnersRounds,
    array $expectedLosersRounds,
    int $expectedWinnersRoundOnePlayerCount,
    int $expectedLosersRoundOnePlayerCount,
) {
    $tournament = Tournament::create([
        'name' => 'Double Elimination Split Participant Test ' . $playerCount,
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => TournamentModeEnum::DOUBLE_ELIMINATION,
        'status' => TournamentStatus::OPEN,
        'split_participant' => true,
    ]);

    $players = collect(range(1, $playerCount))->map(function (int $index) use ($tournament): Player {
        return Player::create([
            'name' => 'Player ' . $index,
            'tournament_id' => $tournament->id,
            'checked_in' => true,
        ]);
    });

    $matches = (new TournamentMatchService())->generateMatches($tournament->id);

    expect($matches)->toHaveCount($expectedTotalMatchCount);

    foreach ($expectedWinnersRounds as $round => $expectedCount) {
        expect($matches->where('round', $round))->toHaveCount($expectedCount);
    }

    foreach ($expectedLosersRounds as $round => $expectedCount) {
        expect($matches->where('round', $round))->toHaveCount($expectedCount);
    }

    $winnersRoundOneParticipants = $matches
        ->where('round', 1)
        ->flatMap(fn($match) => [$match->player1_id, $match->player2_id])
        ->filter()
        ->count();

    expect(
        $winnersRoundOneParticipants
    )->toBe($expectedWinnersRoundOnePlayerCount);

    $losersRoundOneParticipants = $matches
        ->where('round', -1)
        ->flatMap(fn($match) => [$match->player1_id, $match->player2_id])
        ->filter()
        ->count();

    expect($losersRoundOneParticipants)->toBe(
        $expectedLosersRoundOnePlayerCount
    );
})->with('double elimination split participant cases');
