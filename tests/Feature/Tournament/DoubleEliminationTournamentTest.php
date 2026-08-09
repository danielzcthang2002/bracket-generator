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
 *  expectedNormalWinnerMatchCount,
 *  expectedNormalLoserMatchCount,
 *  expectedSplitWinnerMatchCount,
 *  expectedSplitLoserMatchCount
 * ]
 */
dataset('double elimination custom winners bracket cases', [
    // playerCount, winnersRoundsConfig, expectedWbRoundCount
    '8 players, custom 4-2-1' => [8, [1 => 4, 2 => 2, 3 => 1], 3],
    '4 players, custom list [2,1]' => [4, [2, 1], 2],
]);

it('generates a double elimination bracket with a custom winners bracket shape', function (int $playerCount, array $winnersRoundsConfig, int $expectedWbRoundCount) {
    $tournament = Tournament::create([
        'name' => 'Double Elimination Custom WB Test ' . $playerCount,
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => TournamentModeEnum::DOUBLE_ELIMINATION,
        'status' => TournamentStatus::OPEN,
        'split_participant' => false,
    ]);

    collect(range(1, $playerCount))->each(function (int $index) use ($tournament): void {
        Player::create([
            'name' => 'Player ' . $index,
            'tournament_id' => $tournament->id,
            'checked_in' => true,
        ]);
    });

    $matches = (new TournamentMatchService())->generateMatches($tournament->id);

    $normalized = array_is_list($winnersRoundsConfig)
        ? array_combine(range(1, count($winnersRoundsConfig)), array_values($winnersRoundsConfig))
        : $winnersRoundsConfig;

    // Winners bracket rounds (positive) should match the config exactly.
    foreach ($normalized as $round => $expectedCountForRound) {
        expect($matches->where('round', $round))->toHaveCount($expectedCountForRound);
    }

    // Grand final round is totalWbRounds + 1.
    $grandFinal = $matches->where('round', $expectedWbRoundCount + 1)->first();
    expect($grandFinal)->not->toBeNull();
})->with('double elimination custom winners bracket cases');

