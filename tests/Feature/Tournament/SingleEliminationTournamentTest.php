<?php

use App\Enums\TournamentMatchStateEnum;
use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\TournamentMatchService;

/**
 * name => [
 *  playerCount,
 *  roundsConfig (round => matchCount, or null to auto-derive from playerCount),
 *  expectedMatchCount,
 * ]
 */
dataset('single elimination cases', [
    '2 players' => [2, null, 1],
    '3 players' => [3, null, 2],
    '4 players' => [4, null, 3],
    '5 players' => [5, null, 4],
    '6 players' => [6, null, 5],
    '7 players' => [7, null, 6],
    '8 players' => [8, null, 7],
    '9 players' => [9, null, 8],
    '13 players' => [13, null, 12],
    '15 players' => [15, null, 14],
    '16 players' => [16, null, 15],
    '17 players' => [17, null, 16],
    '18 players' => [18, null, 17],
    '19 players' => [19, null, 18],
    '27 players' => [27, null, 26],
    '32 players' => [32, null, 31],
    '39 players' => [39, null, 38],
    '45 players' => [45, null, 44],
    '47 players' => [47, null, 46],
    '55 players' => [55, null, 54],
    '64 players' => [64, null, 63],

    // Explicit custom bracket shapes
    '8 players, custom 4-2-1' => [8, [1 => 4, 2 => 2, 3 => 1], 7],
    '4 players, custom list [2,1]' => [4, [2, 1], 3],
    '16 players, custom 8-4-2-1' => [16, [1 => 8, 2 => 4, 3 => 2, 4 => 1], 15],
]);

it('generates a valid bracket for player count', function (int $playerCount, ?array $roundsConfig, int $expectedMatchCount) {
    $tournament = Tournament::create([
        'name' => 'Single Elimination Test Tournament ' . $playerCount,
        'open_id' => 'SE12345',
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

    $service = new TournamentMatchService();

    $matches = $service->generateMatches($tournament->id);

    $expectedRoundCount = $roundsConfig !== null
        ? count($roundsConfig)
        : (int) ceil(log($playerCount, 2));

    expect($matches)->toHaveCount($expectedMatchCount);
    expect($matches->pluck('suggested_play_order')->all())->toBe(range(1, $expectedMatchCount));
    expect($matches->max('round'))->toBe($expectedRoundCount);

    $finalRoundMatches = $matches->where('round', $expectedRoundCount);
    expect($finalRoundMatches)->toHaveCount(1);

    if ($playerCount === 2 && $roundsConfig === null) {
        expect($finalRoundMatches->first()?->state)->toBe(TournamentMatchStateEnum::OPEN);
    }

    // When a custom shape was requested, verify each round has exactly the
    // number of matches specified.
    if ($roundsConfig !== null) {
        $normalized = array_is_list($roundsConfig)
            ? array_combine(range(1, count($roundsConfig)), array_values($roundsConfig))
            : $roundsConfig;

        foreach ($normalized as $round => $expectedCountForRound) {
            expect($matches->where('round', $round))->toHaveCount($expectedCountForRound);
        }
    }
})->with('single elimination cases');
