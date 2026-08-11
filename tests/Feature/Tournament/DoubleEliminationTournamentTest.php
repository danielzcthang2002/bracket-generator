<?php

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\TournamentMatchService;

dataset('double elimination custom winners bracket cases', [
    '2 players' => [
        'playerCount' => 2,
        'expectedTotalMatchCount' => 2,
        'expectedWinnersRounds' => [1 => 1, 2 => 1],
        'expectedLosersRounds' => [],
        'expectedWinnersRoundOnePlayerCount' => 2,
    ],
    '3 players' => [
        'playerCount' => 3,
        'expectedTotalMatchCount' => 4,
        'expectedWinnersRounds' => [1 => 1, 2 => 1, 3 => 1],
        'expectedLosersRounds' => [-1 => 1],
        'expectedWinnersRoundOnePlayerCount' => 2,
    ],
    '4 players' => [
        'playerCount' => 4,
        'expectedTotalMatchCount' => 6,
        'expectedWinnersRounds' => [1 => 2, 2 => 1, 3 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 1],
        'expectedWinnersRoundOnePlayerCount' => 4,
    ],
    '5 players' => [
        'playerCount' => 5,
        'expectedTotalMatchCount' => 8,
        'expectedWinnersRounds' => [1 => 1, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 1, -3 => 1],
        'expectedWinnersRoundOnePlayerCount' => 2,
    ],
    '6 players' => [
        'playerCount' => 6,
        'expectedTotalMatchCount' => 10,
        'expectedWinnersRounds' => [1 => 2, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 2, -2 => 1, -3 => 1],
        'expectedWinnersRoundOnePlayerCount' => 4,
    ],
    '7 players' => [
        'playerCount' => 7,
        'expectedTotalMatchCount' => 12,
        'expectedWinnersRounds' => [1 => 3, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 2, -3 => 1, -4 => 1],
        'expectedWinnersRoundOnePlayerCount' => 6,
    ],
    '8 players' => [
        'playerCount' => 8,
        'expectedTotalMatchCount' => 14,
        'expectedWinnersRounds' => [1 => 4, 2 => 2, 3 => 1, 4 => 1],
        'expectedLosersRounds' => [-1 => 2, -2 => 2, -3 => 1, -4 => 1],
        'expectedWinnersRoundOnePlayerCount' => 8,
    ],
    '9 players' => [
        'playerCount' => 9,
        'expectedTotalMatchCount' => 16,
        'expectedWinnersRounds' => [1 => 1, 2 => 4, 3 => 2, 4 => 1, 5 => 1],
        'expectedLosersRounds' => [-1 => 1, -2 => 2, -3 => 2, -4 => 1, -5 => 1],
        'expectedWinnersRoundOnePlayerCount' => 2,
    ],
    '10 players' => [
        'playerCount' => 10,
        'expectedTotalMatchCount' => 18,
        'expectedWinnersRounds' => [1 => 2, 2 => 4, 3 => 2, 4 => 1, 5 => 1],
        'expectedLosersRounds' => [-1 => 2, -2 => 2, -3 => 2, -4 => 1, -5 => 1],
        'expectedWinnersRoundOnePlayerCount' => 4,
    ],
]);

it('generates a double elimination bracket with a custom winners bracket shape', function (
    int $playerCount,
    int $expectedTotalMatchCount,
    array $expectedWinnersRounds,
    array $expectedLosersRounds,
    int $expectedWinnersRoundOnePlayerCount,
) {
    $tournament = Tournament::create([
        'name' => 'Double Elimination Custom WB Test ' . $playerCount,
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => TournamentModeEnum::DOUBLE_ELIMINATION,
        'status' => TournamentStatus::OPEN,
        'split_participant' => false,
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
        ->flatMap(fn ($match) => [$match->player1_id, $match->player2_id])
        ->filter()
        ->count();

    expect(
        $winnersRoundOneParticipants
    )->toBe($expectedWinnersRoundOnePlayerCount);
})->with('double elimination custom winners bracket cases');
