<?php

use App\Bracket\Enums\DtTournamentModeEnum;
use App\Bracket\Enums\DtTournamentStatus;
use App\Bracket\Models\DtPlayer;
use App\Bracket\Models\DtTournament;
use App\Bracket\Services\DtTournamentMatchService;

/**
 * Single round-robin via the "circle method": one seat fixed, the rest
 * rotate each round. For odd player counts a bye sentinel is added, which
 * makes exactly one pairing per round a skipped bye - so match count per
 * round is uniform across all rounds: floor(n/2) for even n (over n-1
 * rounds), or (n-1)/2 for odd n (over n rounds, one bye per round).
 */
dataset('round robin cases', [
    '2 players' => [
        'playerCount' => 2,
        'expectedTotalMatchCount' => 1,
        'expectedRounds' => [1 => 1],
        'expectedRoundOnePlayerCount' => 2,
    ],
    '3 players' => [
        'playerCount' => 3,
        'expectedTotalMatchCount' => 3,
        'expectedRounds' => [1 => 1, 2 => 1, 3 => 1],
        'expectedRoundOnePlayerCount' => 2,
    ],
    '4 players' => [
        'playerCount' => 4,
        'expectedTotalMatchCount' => 6,
        'expectedRounds' => [1 => 2, 2 => 2, 3 => 2],
        'expectedRoundOnePlayerCount' => 4,
    ],
    '5 players' => [
        'playerCount' => 5,
        'expectedTotalMatchCount' => 10,
        'expectedRounds' => [1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2],
        'expectedRoundOnePlayerCount' => 4,
    ],
    '6 players' => [
        'playerCount' => 6,
        'expectedTotalMatchCount' => 15,
        'expectedRounds' => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3],
        'expectedRoundOnePlayerCount' => 6,
    ],
    '7 players' => [
        'playerCount' => 7,
        'expectedTotalMatchCount' => 21,
        'expectedRounds' => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 3],
        'expectedRoundOnePlayerCount' => 6,
    ],
    '8 players' => [
        'playerCount' => 8,
        'expectedTotalMatchCount' => 28,
        'expectedRounds' => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4],
        'expectedRoundOnePlayerCount' => 8,
    ],
    '9 players' => [
        'playerCount' => 9,
        'expectedTotalMatchCount' => 36,
        'expectedRounds' => array_fill(1, 9, 4),
        'expectedRoundOnePlayerCount' => 8,
    ],
    '10 players' => [
        'playerCount' => 10,
        'expectedTotalMatchCount' => 45,
        'expectedRounds' => array_fill(1, 9, 5),
        'expectedRoundOnePlayerCount' => 10,
    ],
    '13 players' => [
        'playerCount' => 13,
        'expectedTotalMatchCount' => 78,
        'expectedRounds' => array_fill(1, 13, 6),
        'expectedRoundOnePlayerCount' => 12,
    ],
    '16 players' => [
        'playerCount' => 16,
        'expectedTotalMatchCount' => 120,
        'expectedRounds' => array_fill(1, 15, 8),
        'expectedRoundOnePlayerCount' => 16,
    ],
]);

it('generates a round robin schedule', function (
    int $playerCount,
    int $expectedTotalMatchCount,
    array $expectedRounds,
    int $expectedRoundOnePlayerCount,
) {
    $tournament = DtTournament::create([
        'name' => 'Round Robin Test ' . $playerCount,
        'open_id' => DtTournament::generateOpenId(),
        'mode_type' => DtTournamentModeEnum::ROUND_ROBIN,
        'status' => DtTournamentStatus::OPEN,
    ]);

    $players = collect(range(1, $playerCount))->map(function (int $index) use ($tournament): DtPlayer {
        return DtPlayer::create([
            'name' => 'Player ' . $index,
            'tournament_id' => $tournament->id,
            'checked_in' => true,
            'seed' => $index,
        ]);
    });

    $matches = app(DtTournamentMatchService::class)->generateMatches($tournament->id);

    expect($matches)->toHaveCount($expectedTotalMatchCount);

    foreach ($expectedRounds as $round => $expectedCount) {
        expect($matches->where('round', $round))->toHaveCount($expectedCount);
    }

    // Circle method guarantees each player faces every other player
    // exactly once - no duplicates, no one missed.
    $players->each(function (DtPlayer $player) use ($matches, $playerCount) {
        $opponents = $matches
            ->filter(fn($match) => $match->player1_id === $player->id || $match->player2_id === $player->id)
            ->map(fn($match) => $match->player1_id === $player->id ? $match->player2_id : $match->player1_id);

        expect($opponents->unique())->toHaveCount($playerCount - 1);
    });

    $roundOneParticipants = $matches
        ->where('round', 1)
        ->flatMap(fn($match) => [$match->player1_id, $match->player2_id])
        ->filter()
        ->count();

    expect($roundOneParticipants)->toBe($expectedRoundOnePlayerCount);

    $preview = app(\App\Bracket\Services\DtRoundRobinService::class)->previewBracket($tournament);
    foreach ($matches as $match) {
        $previewMatch = collect($preview[$match->round])
            ->firstWhere('suggested_play_order', $match->suggested_play_order);
        expect($previewMatch['player1']['id'])->toBe($match->player1_id)
            ->and($previewMatch['player2']['id'])->toBe($match->player2_id);
    }

    if ($playerCount >= 3) {
        $seedOneId = $players->first()->id;
        expect($matches->where('player1_id', $seedOneId)->count())->toBeGreaterThan(0)
            ->and($matches->where('player2_id', $seedOneId)->count())->toBeGreaterThan(0);

        if ($playerCount >= 4) {
            $positions = $matches->groupBy('round')->map(fn ($roundMatches) =>
                $roundMatches->values()->search(fn ($match) =>
                    $match->player1_id === $seedOneId || $match->player2_id === $seedOneId
                )
            )->filter(fn ($position) => $position !== false);
            expect($positions->unique()->count())->toBeGreaterThan(1);
        }
    }
})->with('round robin cases');
