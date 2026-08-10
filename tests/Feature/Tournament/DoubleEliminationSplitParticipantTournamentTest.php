<?php

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\TournamentMatchService;

dataset('double elimination split participant cases', [
    '6 players' => [
        'playerCount' => 6,
        'expectedTotalMatchCount' => 8,
        'expectedWinnersRounds' => [1 => 2, 2 => 1, 3 => 1],
        'expectedLosersRounds' => [-1 => 2, -2 => 1, -3 => 1],
        'expectedWinnersRoundOnePlayerCount' => 4,
    ],
]);

it('generates a split participant double elimination bracket', function (
    int $playerCount,
    int $expectedTotalMatchCount,
    array $expectedWinnersRounds,
    array $expectedLosersRounds,
    int $expectedWinnersRoundOnePlayerCount,
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
        ->sort()
        ->values()
        ->all();

    expect($winnersRoundOneParticipants)->toBe(
        $players->take($expectedWinnersRoundOnePlayerCount)->pluck('id')->sort()->values()->all()
    );
})->with('double elimination split participant cases');