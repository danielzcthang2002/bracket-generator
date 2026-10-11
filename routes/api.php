<?php

use App\Http\Controllers\Api\TournamentApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('tournaments', [TournamentApiController::class, 'index'])->name('tournaments.index');
    Route::post('tournaments', [TournamentApiController::class, 'store'])->name('tournaments.store');
    Route::get('tournaments/{openId}', [TournamentApiController::class, 'show'])->name('tournaments.show');
    Route::put('tournaments/{openId}', [TournamentApiController::class, 'update'])->name('tournaments.update');

    Route::get('tournaments/{openId}/participants', [TournamentApiController::class, 'getParticipants'])->name('participants.index');
    Route::post('tournaments/{openId}/participants', [TournamentApiController::class, 'addPlayer'])->name('participants.store');
    Route::delete('tournaments/{openId}/participants', [TournamentApiController::class, 'removePlayers'])->name('participants.bulk-destroy');
    Route::delete('tournaments/{openId}/participants/{playerId}', [TournamentApiController::class, 'removePlayer'])->name('participants.destroy');
    Route::patch('tournaments/{openId}/participants/{playerId}/check-in', [TournamentApiController::class, 'updatePlayerCheckIn'])->name('participants.check-in');
    Route::post('tournaments/{openId}/participants/process-check-ins', [TournamentApiController::class, 'processCheckedInPlayers'])->name('participants.process-check-ins');

    Route::get('tournaments/{openId}/matches', [TournamentApiController::class, 'getMatches'])->name('matches.index');
    Route::post('tournaments/{openId}/matches/generate', [TournamentApiController::class, 'generateMatches'])->name('matches.generate');
    Route::put('tournaments/{openId}/matches/{matchId}/score', [TournamentApiController::class, 'updateMatchScores'])->name('matches.score');

    Route::post('tournaments/{openId}/actions/start', [TournamentApiController::class, 'startTournament'])->name('tournaments.start');
    Route::post('tournaments/{openId}/actions/end', [TournamentApiController::class, 'endTournament'])->name('tournaments.end');
});
