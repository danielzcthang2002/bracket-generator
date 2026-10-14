<?php

use App\Bracket\Enums\DtTournamentMatchStateEnum;
use App\Bracket\Models\DtPlayer;
use App\Bracket\Models\DtTournament;
use App\Bracket\Services\DtTournamentMatchService;

function doubleRollbackTournament(int $count, bool $split): DtTournament
{
    $tournament = DtTournament::create([
        'name' => 'Double rollback', 'open_id' => DtTournament::generateOpenId(),
        'mode_type' => 'double_elimination', 'status' => 'open', 'split_participant' => $split,
    ]);
    foreach (range(1, $count) as $index) {
        DtPlayer::create(['name' => 'Player '.$index, 'tournament_id' => $tournament->id, 'checked_in' => true]);
    }
    app(DtTournamentMatchService::class)->generateMatches($tournament->id);
    return $tournament;
}

function finishDoubleRollback(DtTournament $tournament): void
{
    while ($match = $tournament->matches()->where('state', 'open')->orderBy('suggested_play_order')->first()) {
        app(DtTournamentMatchService::class)->updateMatchScores($match->id, '3-1', $match->player1_id);
    }
}

it('clears both dependent paths while preserving unrelated results', function (int $count, bool $split, bool $losers) {
    $tournament = doubleRollbackTournament($count, $split);
    finishDoubleRollback($tournament);
    $matches = $tournament->matches()->orderBy('suggested_play_order')->get();
    $changed = $losers ? $matches->first(fn ($match) => $match->round < 0) : $matches->first();
    $affected = [$changed->id];
    do {
        $previous = $affected;
        foreach ($matches as $match) {
            if (in_array($match->player1_prereq_match_id, $affected) || in_array($match->player2_prereq_match_id, $affected)) {
                $affected[] = $match->id;
            }
        }
        $affected = array_values(array_unique($affected));
    } while ($previous !== $affected);
    $unrelated = $matches->whereNotIn('id', $affected)->mapWithKeys(fn ($match) => [
        $match->id => [$match->winner_id, $match->loser_id, $match->matchScores()->get()->toArray()],
    ]);
    app(DtTournamentMatchService::class)->updateMatchScores($changed->id, '1-3', $changed->player2_id);
    foreach (array_slice($affected, 1) as $id) {
        $match = $tournament->matches()->find($id);
        expect($match)->not->toBeNull();
        expect($match->winner_id)->toBeNull()->and($match->loser_id)->toBeNull()
            ->and($match->player1_score)->toBeNull()->and($match->player2_score)->toBeNull()
            ->and($match->matchScores()->count())->toBe(0)
            ->and($match->state)->toBe($match->player1_id && $match->player2_id ? DtTournamentMatchStateEnum::OPEN : DtTournamentMatchStateEnum::PENDING);
        foreach ([1, 2] as $slot) {
            if ($match->{'player'.$slot.'_prereq_match_id'} === $changed->id) {
                expect($match->{'player'.$slot.'_id'})->toBe($match->{'player'.$slot.'_is_prereq_match_loser'} ? $changed->player1_id : $changed->player2_id);
            }
        }
    }
    foreach ($unrelated as $id => $result) {
        $match = $tournament->matches()->findOrFail($id);
        expect([$match->winner_id, $match->loser_id, $match->matchScores()->get()->toArray()])->toBe($result);
    }
    expect($tournament->players()->whereNotNull('final_rank')->count())->toBe(0);
    finishDoubleRollback($tournament);
    expect($tournament->matches()->whereNull('winner_id')->count())->toBe(0)
        ->and($tournament->players()->where('final_rank', 1)->count())->toBe(1);
})->with([[8, false, false], [5, false, false], [8, true, false], [5, true, false], [8, false, true], [8, true, true]]);

it('creates and removes the reset final when the first final winner changes', function () {
    $tournament = doubleRollbackTournament(4, false);
    finishDoubleRollback($tournament);
    $service = app(DtTournamentMatchService::class);
    $final = $tournament->matches()->orderByDesc('suggested_play_order')->first();
    $service->updateMatchScores($final->id, '1-3', $final->player2_id);
    $reset = $tournament->matches()->orderByDesc('suggested_play_order')->first();
    expect($reset->id)->not->toBe($final->id)
        ->and($reset->state)->toBe(DtTournamentMatchStateEnum::OPEN)
        ->and($tournament->players()->whereNotNull('final_rank')->count())->toBe(0);
    $service->updateMatchScores($reset->id, '3-1', $reset->player1_id);
    $service->updateMatchScores($final->id, '2-4', $final->player2_id);
    expect($reset->fresh()->winner_id)->toBe($reset->player1_id);
    $service->updateMatchScores($final->id, '3-1', $final->player1_id);
    expect($reset->fresh())->toBeNull()
        ->and($reset->matchScores()->count())->toBe(0)
        ->and($tournament->players()->find($final->player1_id)->final_rank)->toBe(1);
    $service->updateMatchScores($final->id, '1-3', $final->player2_id);
    expect($tournament->players()->whereNotNull('final_rank')->count())->toBe(0);
});

it('removes a completed reset final when an earlier result changes', function () {
    $tournament = doubleRollbackTournament(8, false);
    finishDoubleRollback($tournament);
    $service = app(DtTournamentMatchService::class);
    $final = $tournament->matches()->orderByDesc('suggested_play_order')->first();
    $service->updateMatchScores($final->id, '1-3', $final->player2_id);
    $reset = $tournament->matches()->orderByDesc('suggested_play_order')->first();
    $service->updateMatchScores($reset->id, '3-1', $reset->player1_id);
    $first = $tournament->matches()->orderBy('suggested_play_order')->first();
    $service->updateMatchScores($first->id, '1-3', $first->player2_id);
    expect($reset->fresh())->toBeNull()
        ->and($reset->matchScores()->count())->toBe(0)
        ->and($final->fresh()->winner_id)->toBeNull()
        ->and($final->fresh()->state)->toBe(DtTournamentMatchStateEnum::PENDING)
        ->and($tournament->players()->whereNotNull('final_rank')->count())->toBe(0);
});
