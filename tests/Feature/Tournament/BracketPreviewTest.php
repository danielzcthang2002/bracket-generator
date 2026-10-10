<?php

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Bracket\Contracts\ModeResolver;
use Inertia\Testing\AssertableInertia as Assert;

it('previews deterministic matches without writing data', function (TournamentModeEnum $mode, int $count, bool $split) {
    $tournament = Tournament::create([
        'name' => 'Preview test',
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => $mode,
        'status' => TournamentStatus::PENDING,
        'split_participant' => $split,
    ]);
    for ($i = 1; $i <= $count; $i++) {
        Player::create([
            'name' => "Player {$i}",
            'tournament_id' => $tournament->id,
            'checked_in' => true,
        ]);
    }

    $service = app(ModeResolver::class)->resolve($mode->value);
    $preview = $service->previewBracket($tournament);
    expect($preview)->not->toBeEmpty();
    expect($tournament->matches()->count())->toBe(0);

    $matches = $service->initialize($tournament);
    $previewRows = collect($preview)->flatten(1)->keyBy('suggested_play_order');
    if (in_array($mode, [TournamentModeEnum::SWISS, TournamentModeEnum::FREE_FOR_ALL], true)) {
        $matches = $matches->where('round', 1);
    }

    foreach ($matches as $match) {
        $row = $previewRows->get($match->suggested_play_order);
        expect($row)->not->toBeNull();
        expect($row['round'])->toBe($match->round);
        if ($mode === TournamentModeEnum::FREE_FOR_ALL) {
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
    [TournamentModeEnum::SINGLE_ELIMINATION, 5, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 2, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 3, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 4, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 5, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 6, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 6, true],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 7, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 7, true],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 8, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 9, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 9, true],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 10, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 13, false],
    [TournamentModeEnum::DOUBLE_ELIMINATION, 23, false],
    [TournamentModeEnum::ROUND_ROBIN, 5, false],
    [TournamentModeEnum::SWISS, 5, false],
    [TournamentModeEnum::FREE_FOR_ALL, 7, false],
]);

it('shows a preview on the tournament page before play starts', function () {
    $tournament = Tournament::create([
        'name' => 'Preview page test',
        'open_id' => Tournament::generateOpenId(),
        'mode_type' => TournamentModeEnum::ROUND_ROBIN,
        'status' => TournamentStatus::PENDING,
    ]);
    foreach (['Alice', 'Bob'] as $name) {
        Player::create([
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
