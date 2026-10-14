<?php

use App\Bracket\Models\DtMatchScore;
use App\Bracket\Models\DtPlayer;
use App\Bracket\Models\DtTournament;
use App\Bracket\Models\DtTournamentMatch;
use App\Bracket\Models\DtTournamentMatchParticipant;
use Illuminate\Support\Facades\Schema;

it('isolates bracket tables and preserves relationship foreign keys after prefixing models', function () {
    foreach (['tournaments', 'players', 'tournament_matches', 'match_scores', 'tournament_match_participants'] as $table) {
        expect(Schema::hasTable('dt_'.$table))->toBeTrue()
            ->and(Schema::hasTable($table))->toBeFalse();
    }

    $tournament = DtTournament::create(['name' => 'Prefix test', 'open_id' => 'PREFIX', 'mode_type' => 'single_elimination']);
    $player = $tournament->players()->create(['name' => 'Player', 'seed' => 1]);
    $match = $tournament->matches()->create(['player1_id' => $player->id, 'round' => 1, 'suggested_play_order' => 1]);
    $score = $player->matchScores()->create(['tournament_match_id' => $match->id, 'set' => 1, 'score' => 2]);
    $participant = $match->participants()->create(['player_id' => $player->id, 'position' => 1]);

    expect($player)->toBeInstanceOf(DtPlayer::class)
        ->and($match)->toBeInstanceOf(DtTournamentMatch::class)
        ->and($score)->toBeInstanceOf(DtMatchScore::class)
        ->and($participant)->toBeInstanceOf(DtTournamentMatchParticipant::class)
        ->and($player->tournament->is($tournament))->toBeTrue()
        ->and($match->tournament->is($tournament))->toBeTrue()
        ->and($score->player->is($player))->toBeTrue()
        ->and($score->tournamentMatch->is($match))->toBeTrue()
        ->and($participant->player->is($player))->toBeTrue()
        ->and($participant->tournamentMatch->is($match))->toBeTrue()
        ->and($match->matchScores()->count())->toBe(1);
});
