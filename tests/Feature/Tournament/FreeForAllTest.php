<?php

use App\Enums\TournamentMatchStateEnum;
use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\TournamentMatchService;

/**
 * Free-for-all is multi-round like Swiss (round N+1 depends on round N's
 * results), but heats/ranks instead of 1v1 matches make the state to track
 * heavier. Rather than hand-deriving expected heat shapes and final ranks
 * per case, this file ports FreeForAllService's own algorithm (buildHeats,
 * advancement, tie-break ordering, final-rank tiering) into a standalone
 * PHP reference simulator, then drives the real service in lockstep against
 * it - asserting exact heat composition every round and exact final ranks
 * at the end, not just "it's a permutation".
 *
 * The tie-break ordering (rank, then seed) assumes every test player has a
 * null seed, so ties fall back to PHP's sort stability (guaranteed since
 * PHP 8.0) over the id-ascending list the service's own queries return
 * absent an explicit orderBy - the same assumption the Swiss test already
 * relies on for its seed-tie fallback.
 */

function ffaTournament(int $playerCount, ?int $heatSize = null, ?int $advanceCount = null): array
{
    $attributes = [
        'name' => 'Free For All Test ' . $playerCount,
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => TournamentModeEnum::FREE_FOR_ALL,
        'status' => TournamentStatus::OPEN,
    ];

    if ($heatSize !== null) {
        $attributes['ffa_heat_size'] = $heatSize;
    }
    if ($advanceCount !== null) {
        $attributes['ffa_advance_count'] = $advanceCount;
    }

    $tournament = Tournament::create($attributes);

    $players = collect(range(1, $playerCount))->map(function (int $index) use ($tournament): Player {
        return Player::create([
            'name' => 'Player ' . $index,
            'tournament_id' => $tournament->id,
            'checked_in' => true,
        ]);
    });

    return [$tournament, $players];
}

/** Mirrors FreeForAllService::buildHeats() exactly. */
function ffaBuildHeats(array $orderedIds, int $heatSize): array
{
    $n = count($orderedIds);
    if ($n <= $heatSize) {
        return [$orderedIds];
    }

    $numHeats = (int) ceil($n / $heatSize);
    $heats = array_fill(0, $numHeats, []);
    $heatIndex = 0;
    $direction = 1;

    foreach ($orderedIds as $id) {
        $heats[$heatIndex][] = $id;
        $next = $heatIndex + $direction;
        if ($next < 0 || $next >= $numHeats) {
            $direction *= -1;
        } else {
            $heatIndex = $next;
        }
    }

    return $heats;
}

/**
 * Full reference simulation mirroring the service's advancement, ordering,
 * and final-rank-tiering logic. "Rank" within a heat is defined as the
 * player's position in that heat's ordered id list (1-indexed) - the same
 * rule the test uses when reporting results back to the real service, so
 * the two stay in lockstep.
 *
 * @return array{rounds: array<int, array<int, array<int,int>>>, finalRanks: array<int,int>}
 */
function ffaSimulate(array $orderedIds, int $heatSize, int $advanceCount): array
{
    $rounds = [];
    $current = $orderedIds;

    while (true) {
        $heats = ffaBuildHeats($current, $heatSize);
        $rounds[] = $heats;

        if (count($heats) === 1) {
            break;
        }

        $advancerIds = [];
        $rankMap = [];

        foreach ($heats as $heat) {
            $count = count($heat);
            $take = $count <= 1 ? $count : max(1, min($advanceCount, $count - 1));

            foreach ($heat as $idx => $id) {
                $rankMap[$id] = $idx + 1;
            }

            for ($i = 0; $i < $take; $i++) {
                $advancerIds[] = $heat[$i];
            }
        }

        sort($advancerIds);
        usort($advancerIds, fn($a, $b) => $rankMap[$a] <=> $rankMap[$b]);
        $current = $advancerIds;
    }

    $finalRanks = [];
    $rank = 1;
    foreach ($rounds[count($rounds) - 1][0] as $id) {
        $finalRanks[$id] = $rank++;
    }

    for ($r = count($rounds) - 2; $r >= 0; $r--) {
        $nextRoundIds = [];
        foreach ($rounds[$r + 1] as $heat) {
            foreach ($heat as $id) {
                $nextRoundIds[] = $id;
            }
        }
        $advancerSet = array_flip($nextRoundIds);

        $eliminated = [];
        foreach ($rounds[$r] as $heat) {
            foreach ($heat as $idx => $id) {
                if (!isset($advancerSet[$id])) {
                    $eliminated[] = ['id' => $id, 'rank' => $idx + 1];
                }
            }
        }
        usort($eliminated, fn($a, $b) => $a['rank'] <=> $b['rank']);

        foreach ($eliminated as $e) {
            $finalRanks[$e['id']] = $rank++;
        }
    }

    return ['rounds' => $rounds, 'finalRanks' => $finalRanks];
}

function ffaAdvanceCountFor(int $heatSize, ?int $configured = null): int
{
    $c = $configured ?? (int) floor($heatSize * 0.5);
    return min(max(1, $c), $heatSize - 1);
}

/** Report every not-yet-ranked participant in a round: rank = draft position. */
function reportFfaRound(TournamentMatch $tournament_match_placeholder = null): void
{
    // placeholder to keep static analyzers quiet about unused import above
}

dataset('free for all round one cases', [
    '4 players, default heat size' => ['playerCount' => 4, 'heatSize' => null],
    '8 players, default heat size' => ['playerCount' => 8, 'heatSize' => null],
    '9 players, default heat size' => ['playerCount' => 9, 'heatSize' => null],
    '10 players, default heat size' => ['playerCount' => 10, 'heatSize' => null],
    '5 players, heat size 3' => ['playerCount' => 5, 'heatSize' => 3],
    '3 players, heat size 2 (bye heat)' => ['playerCount' => 3, 'heatSize' => 2],
]);

it('builds round 1 heats via snake draft off seed order', function (int $playerCount, ?int $heatSize) {
    [$tournament, $players] = ffaTournament($playerCount, $heatSize);
    $resolvedHeatSize = max(2, $heatSize ?? 4);

    $matches = (new TournamentMatchService())->generateMatches($tournament->id);

    $expectedHeats = ffaBuildHeats($players->pluck('id')->all(), $resolvedHeatSize);

    $roundOne = $matches->where('round', 1)->sortBy('suggested_play_order')->values();
    expect($roundOne)->toHaveCount(count($expectedHeats));

    foreach ($expectedHeats as $i => $expectedIds) {
        $actualIds = $roundOne[$i]->participants->sortBy('position')->pluck('player_id')->all();
        expect($actualIds)->toBe($expectedIds);

        if (count($expectedIds) === 1) {
            // Automatic bye: instantly ranked and complete.
            $participant = $roundOne[$i]->participants->first();
            expect($participant->rank)->toBe(1);
            expect($participant->is_winner)->toBeTrue();
            expect($roundOne[$i]->state)->toBe(TournamentMatchStateEnum::COMPLETE);
        }
    }
})->with('free for all round one cases');

dataset('free for all full progression cases', [
    '4 players, default heat size (single-heat final)' => ['playerCount' => 4, 'heatSize' => null],
    '8 players, default heat size' => ['playerCount' => 8, 'heatSize' => null],
    '9 players, default heat size' => ['playerCount' => 9, 'heatSize' => null],
    '5 players, heat size 3' => ['playerCount' => 5, 'heatSize' => 3],
    '3 players, heat size 2 (bye heat)' => ['playerCount' => 3, 'heatSize' => 2],
    '16 players, default heat size' => ['playerCount' => 16, 'heatSize' => null],
]);

it('advances heats round by round and assigns exact final ranks', function (int $playerCount, ?int $heatSize) {
    [$tournament, $players] = ffaTournament($playerCount, $heatSize);
    $resolvedHeatSize = max(2, $heatSize ?? 4);
    $advanceCount = ffaAdvanceCountFor($resolvedHeatSize);

    $reference = ffaSimulate($players->pluck('id')->all(), $resolvedHeatSize, $advanceCount);
    $service = new TournamentMatchService();

    foreach ($reference['rounds'] as $roundIndex => $expectedHeats) {
        $round = $roundIndex + 1;
        $matches = $service->generateMatches($tournament->id);

        expect((int) $tournament->matches()->max('round'))->toBe($round);

        $roundMatches = $matches->where('round', $round)->sortBy('suggested_play_order')->values();
        expect($roundMatches)->toHaveCount(count($expectedHeats));

        foreach ($expectedHeats as $i => $expectedIds) {
            $match = $roundMatches[$i];
            $actualIds = $match->participants->sortBy('position')->pluck('player_id')->all();
            expect($actualIds)->toBe($expectedIds);

            // Report results in draft-position order, skipping any
            // participant already ranked (i.e. an automatic bye heat).
            foreach ($match->participants->sortBy('position')->values() as $idx => $participant) {
                if ($participant->rank !== null) {
                    continue;
                }
                $participant->update([
                    'rank' => $idx + 1,
                    'is_winner' => $idx === 0,
                ]);
            }

            $match->update(['state' => TournamentMatchStateEnum::COMPLETE]);
        }
    }

    // One more call: the last generated round was the single-heat final,
    // now reported - this call should assign final ranks without creating
    // a new round.
    $service->generateMatches($tournament->id);

    expect((int) $tournament->matches()->max('round'))->toBe(count($reference['rounds']));

    $actualRanks = $tournament->players()->checkedIn()->get()
        ->mapWithKeys(fn($p) => [$p->id => $p->final_rank])
        ->all();

    $expectedRanks = $reference['finalRanks'];

    // Same content, but built via different insertion orders (elimination-
    // tier order here vs. id-ascending from the DB query) - sort both by
    // key so the comparison checks values, not incidental array order.
    ksort($actualRanks);
    ksort($expectedRanks);

    expect($actualRanks)->toBe($expectedRanks);
})->with('free for all full progression cases');
