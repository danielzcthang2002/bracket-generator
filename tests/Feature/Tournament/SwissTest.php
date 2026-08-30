<?php

use App\Enums\TournamentMatchStateEnum;
use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\TournamentMatchService;
use Illuminate\Support\Collection;

/**
 * Swiss can't be generated all at once like the other modes - round N+1
 * depends on round N's results - so these tests differ in shape from the
 * single/double elimination and round robin tests: round 1 is checked with
 * a single call (dataset-driven, like the others), and full progression is
 * checked by simulating results and calling generateMatches() again between
 * rounds.
 */

function swissTournament(int $playerCount): array
{
    $tournament = Tournament::create([
        'name' => 'Swiss Test ' . $playerCount,
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => TournamentModeEnum::SWISS,
        'status' => TournamentStatus::OPEN,
        'points_per_match_win' => 1,
        'points_per_match_tie' => 0.5,
        'points_per_set_win' => 0,
        'points_per_set_tie' => 0,
        'points_per_bye' => 1,
    ]);

    $players = collect(range(1, $playerCount))->map(function (int $index) use ($tournament): Player {
        return Player::create([
            'name' => 'Player ' . $index,
            'tournament_id' => $tournament->id,
            'checked_in' => true,
        ]);
    });

    return [$tournament, $players];
}

/**
 * Reimplements pairFold()'s expected output given the same id-ordered list
 * the service would see, so round 1 can be checked structurally rather
 * than by hardcoding ids per case.
 *
 * @return array{0: array<int, array{0:int,1:int}>, 1: int|null}
 */
function expectedFoldPairing(Collection $orderedIds): array
{
    $ids = $orderedIds->values();
    $bye = null;

    if ($ids->count() % 2 === 1) {
        $bye = $ids->pop();
    }

    $half = intdiv($ids->count(), 2);
    $top = $ids->slice(0, $half)->values();
    $bottom = $ids->slice($half)->values();

    $pairs = [];
    for ($i = 0; $i < $half; $i++) {
        $pairs[] = [$top[$i], $bottom[$i]];
    }

    return [$pairs, $bye];
}

function pairKey(int $a, int $b): string
{
    return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
}

/** Mark every real (non-bye) match in a round as decided, player1 winning. */
function completeSwissRound(Tournament $tournament, int $round): void
{
    TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('round', $round)
        ->whereNotNull('player2_id', 'and')
        ->get()
        ->each(function (TournamentMatch $match) {
            $match->update([
                'winner_id' => $match->player1_id,
                'is_tie' => false,
                'state' => TournamentMatchStateEnum::COMPLETE,
            ]);
        });
}

dataset('swiss round one cases', [
    '4 players' => ['playerCount' => 4, 'expectedMatchCount' => 2, 'hasBye' => false],
    '5 players' => ['playerCount' => 5, 'expectedMatchCount' => 2, 'hasBye' => true],
    '6 players' => ['playerCount' => 6, 'expectedMatchCount' => 3, 'hasBye' => false],
    '7 players' => ['playerCount' => 7, 'expectedMatchCount' => 3, 'hasBye' => true],
    '8 players' => ['playerCount' => 8, 'expectedMatchCount' => 4, 'hasBye' => false],
    '9 players' => ['playerCount' => 9, 'expectedMatchCount' => 4, 'hasBye' => true],
]);

it('generates round 1 via fold seeding', function (
    int $playerCount,
    int $expectedMatchCount,
    bool $hasBye,
) {
    [$tournament, $players] = swissTournament($playerCount);

    $matches = (new TournamentMatchService())->generateMatches($tournament->id);

    expect($matches)->toHaveCount($hasBye ? $expectedMatchCount + 1 : $expectedMatchCount);
    expect($matches->where('round', 1))->toHaveCount($hasBye ? $expectedMatchCount + 1 : $expectedMatchCount);
    expect((int) $tournament->matches()->max('round'))->toBe(1);

    [$expectedPairs, $expectedByeId] = expectedFoldPairing($players->pluck('id'));

    $realMatches = $matches->whereNotNull('player2_id');
    expect($realMatches)->toHaveCount($expectedMatchCount);

    $actualPairKeys = $realMatches
        ->map(fn($match) => pairKey($match->player1_id, $match->player2_id))
        ->sort()
        ->values()
        ->all();

    $expectedPairKeys = collect($expectedPairs)
        ->map(fn($pair) => pairKey($pair[0], $pair[1]))
        ->sort()
        ->values()
        ->all();

    expect($actualPairKeys)->toBe($expectedPairKeys);

    $byeMatch = $matches->whereNull('player2_id')->first();

    if ($hasBye) {
        expect($byeMatch)->not->toBeNull();
        expect($byeMatch->player1_id)->toBe($expectedByeId);
        expect($byeMatch->winner_id)->toBe($expectedByeId);
        expect($byeMatch->state)->toBe(TournamentMatchStateEnum::COMPLETE);
    } else {
        expect($byeMatch)->toBeNull();
    }
})->with('swiss round one cases');

dataset('swiss full progression cases', [
    '4 players' => ['playerCount' => 4, 'expectedTotalRounds' => 2, 'expectedMatchesPerRound' => 2, 'allowsForcedRematch' => false],
    '5 players' => ['playerCount' => 5, 'expectedTotalRounds' => 3, 'expectedMatchesPerRound' => 2, 'allowsForcedRematch' => true],
    '7 players' => ['playerCount' => 7, 'expectedTotalRounds' => 3, 'expectedMatchesPerRound' => 3, 'allowsForcedRematch' => false],
    '8 players' => ['playerCount' => 8, 'expectedTotalRounds' => 3, 'expectedMatchesPerRound' => 4, 'allowsForcedRematch' => false],
]);

it('advances one round at a time until final ranks are assigned', function (
    int $playerCount,
    int $expectedTotalRounds,
    int $expectedMatchesPerRound,
    bool $allowsForcedRematch,
) {
    [$tournament, $players] = swissTournament($playerCount);
    $service = new TournamentMatchService();
    $isOdd = $playerCount % 2 === 1;

    $seenPairKeys = [];
    $byeRecipients = [];
    $rematchCount = 0;

    for ($round = 1; $round <= $expectedTotalRounds; $round++) {
        $matches = $service->generateMatches($tournament->id);

        expect((int) $tournament->matches()->max('round'))->toBe($round);

        $roundMatches = $matches->where('round', $round);
        $realMatches = $roundMatches->whereNotNull('player2_id');
        $byeMatch = $roundMatches->whereNull('player2_id')->first();

        expect($realMatches)->toHaveCount($expectedMatchesPerRound);
        expect($byeMatch !== null)->toBe($isOdd);

        // Pairings shouldn't repeat across the event UNLESS the field is
        // small enough, relative to the round count, that the pairing
        // algorithm's documented no-look-ahead fallback is forced to
        // rematch someone (see SwissService::pairSwiss docblock). Track
        // rematches instead of hard-failing on them for cases where
        // that's expected.
        foreach ($realMatches as $match) {
            $key = pairKey($match->player1_id, $match->player2_id);
            if (isset($seenPairKeys[$key])) {
                $rematchCount++;
                if (!$allowsForcedRematch) {
                    expect($seenPairKeys)->not->toHaveKey($key);
                }
            }
            $seenPairKeys[$key] = true;
        }

        if ($byeMatch) {
            if (count($byeRecipients) < $playerCount) {
                expect($byeRecipients)->not->toHaveKey($byeMatch->player1_id);
            }
            $byeRecipients[$byeMatch->player1_id] = true;
        }

        completeSwissRound($tournament, $round);
    }

    if ($allowsForcedRematch) {
        // Sanity check: this dataset case exists specifically because a
        // rematch is unavoidable here - if the algorithm changes and this
        // stops happening, the flag (and this assertion) should be revisited.
        expect($rematchCount)->toBeGreaterThan(0);
    }

    $service->generateMatches($tournament->id);

    expect((int) $tournament->matches()->max('round'))->toBe($expectedTotalRounds);

    $ranks = $tournament->players()->checkedIn()->pluck('final_rank')->sort()->values()->all();
    expect($ranks)->toBe(range(1, $playerCount));
})->with('swiss full progression cases');
