<?php

use App\Bracket\Enums\DtTournamentModeEnum;
use App\Bracket\Enums\DtTournamentStatus;
use App\Bracket\Models\DtPlayer;
use App\Bracket\Models\DtTournament;
use App\Models\User;
use App\Bracket\Contracts\DtModeResolver;
use Inertia\Testing\AssertableInertia as Assert;

it('includes unchecked players in previews only when check-in is optional', function (bool $requireCheckIn) {
    $tournament = DtTournament::create([
        'name' => 'Check-in preview',
        'open_id' => DtTournament::generateOpenId(),
        'mode_type' => DtTournamentModeEnum::SINGLE_ELIMINATION,
        'status' => DtTournamentStatus::PENDING,
        'require_check_in' => $requireCheckIn,
    ]);
    $first = $tournament->players()->create(['name' => 'First', 'seed' => 1, 'checked_in' => true]);
    $second = $tournament->players()->create(['name' => 'Second', 'seed' => 2, 'checked_in' => true]);
    $unchecked = $tournament->players()->create(['name' => 'Unchecked', 'seed' => 3, 'checked_in' => false]);

    $preview = app(DtModeResolver::class)->resolve('single_elimination')->previewBracket($tournament);
    $playerIds = collect($preview)->flatten(1)
        ->flatMap(fn ($match) => [$match['player1'], $match['player2']])
        ->where('type', 'player')->pluck('id')->sort()->values()->all();

    expect($playerIds)->toBe($requireCheckIn ? [$first->id, $second->id] : [$first->id, $second->id, $unchecked->id]);
})->with([true, false]);

it('previews deterministic matches without writing data', function (DtTournamentModeEnum $mode, int $count, bool $split) {
    $tournament = DtTournament::create([
        'name' => 'Preview test',
        'open_id' => DtTournament::generateOpenId(),
        'mode_type' => $mode,
        'status' => DtTournamentStatus::PENDING,
        'split_participant' => $split,
    ]);
    for ($i = 1; $i <= $count; $i++) {
        DtPlayer::create([
            'name' => "Player {$i}",
            'tournament_id' => $tournament->id,
            'checked_in' => true,
        ]);
    }

    $service = app(DtModeResolver::class)->resolve($mode->value);
    $preview = $service->previewBracket($tournament);
    expect($preview)->not->toBeEmpty();
    expect($tournament->matches()->count())->toBe(0);

    $matches = $service->initialize($tournament);
    $previewRows = collect($preview)->flatten(1)->keyBy('suggested_play_order');
    if (in_array($mode, [DtTournamentModeEnum::SWISS, DtTournamentModeEnum::FREE_FOR_ALL], true)) {
        $matches = $matches->where('round', 1);
    }

    foreach ($matches as $match) {
        $row = $previewRows->get($match->suggested_play_order);
        expect($row)->not->toBeNull();
        expect($row['round'])->toBe($match->round);
        if ($mode === DtTournamentModeEnum::FREE_FOR_ALL) {
            expect(array_column($row['participants'], 'id'))
                ->toBe($match->participants->sortBy('position')->pluck('player_id')->all());
            continue;
        }
        foreach ([1, 2] as $slot) {
            $playerId = $match->{"player{$slot}_id"};
            $prereqId = $match->{"player{$slot}_prereq_match_id"};
            $previewSlot = $row["player{$slot}"];
            if ($prereqId !== null) {
                $prereqOrder = $matches->firstWhere('id', $prereqId)?->suggested_play_order;
                expect($previewSlot['prereq_suggested_play_order'])->toBe($prereqOrder);
            } elseif ($playerId !== null) {
                expect($previewSlot['id'])->toBe($playerId);
            }
        }
    }
})->with([
    [DtTournamentModeEnum::SINGLE_ELIMINATION, 5, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 2, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 3, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 4, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 5, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 6, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 6, true],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 7, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 7, true],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 8, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 9, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 9, true],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 10, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 13, false],
    [DtTournamentModeEnum::DOUBLE_ELIMINATION, 23, false],
    [DtTournamentModeEnum::ROUND_ROBIN, 5, false],
    [DtTournamentModeEnum::SWISS, 5, false],
    [DtTournamentModeEnum::FREE_FOR_ALL, 7, false],
]);

it('shows a preview on the tournament page before play starts', function () {
    $tournament = DtTournament::create([
        'name' => 'Preview page test',
        'open_id' => DtTournament::generateOpenId(),
        'mode_type' => DtTournamentModeEnum::ROUND_ROBIN,
        'status' => DtTournamentStatus::PENDING,
    ]);
    foreach (['Alice', 'Bob'] as $name) {
        DtPlayer::create([
            'name' => $name,
            'tournament_id' => $tournament->id,
            'checked_in' => true,
        ]);
    }

    $this->actingAs(User::factory()->create())
        ->get(route('tournament.show', $tournament->open_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tournament/tournament-show')
            ->has('bracketPreview.1', 1)
            ->where('bracketPreview.1.0.player1.name', 'Alice')
            ->etc());

    expect($tournament->matches()->count())->toBe(0);
});
