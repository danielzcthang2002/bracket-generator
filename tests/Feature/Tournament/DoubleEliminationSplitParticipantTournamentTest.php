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
        'expectedLosersRoundOnePlayerCount' =>1,
    ],
    '4 players' => [
        'playerCount' => 4,
        'expectedTotalMatchCount' => 4,
        'expectedWinnersRounds' => [1 => 1, 2 => 1,],
        'expectedLosersRounds' => [-1 => 1, 2 => 1],
        'expectedWinnersRoundOnePlayerCount' => 2,
        'expectedLosersRoundOnePlayerCount' => 2,
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
        ->flatMap(fn ($match) => [$match->player1_id, $match->player2_id])
        ->filter()
        ->count();

    expect(
        $winnersRoundOneParticipants
    )->toBe($expectedWinnersRoundOnePlayerCount);

    $losersRoundOneParticipants = $matches
        ->where('round', -1)
        ->flatMap(fn ($match) => [$match->player1_id, $match->player2_id])
        ->filter()
        ->count();

    expect($losersRoundOneParticipants)->toBe(
            $expectedLosersRoundOnePlayerCount
        );
})->with('double elimination split participant cases');