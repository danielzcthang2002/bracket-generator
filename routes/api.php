<?php

use App\Http\Controllers\Api\TournamentApiController;
use App\Services\TournamentMatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {

    Route::get('/tournaments.json', [TournamentApiController::class, 'index']);
    // Route::get('/{id}', [TournamentApiController::class, 'show']);

    // POST and PUT methods for creating and updating tournaments
    Route::post('/tournaments.json', [TournamentApiController::class, 'store']);
    // Route::put('/{id}', [TournamentApiController::class, 'update']);

    // Route::get('/{openId}/players', [TournamentApiController::class, 'getPlayers']);
    Route::post('/tournaments/{openId}/participants.json', [TournamentApiController::class, 'addPlayer']);
    Route::get('/tournaments/{openId}/participants.json', [TournamentApiController::class, 'getParticipants']);


    Route::delete('/tournaments/{openId}/participants/{playerId}.json', [TournamentApiController::class, 'removePlayer']);

    Route::post('/tournaments/{openId}/participants/{playerId}/check_in.json', [TournamentApiController::class, 'checkInPlayer']);
    Route::post('/tournaments/{openId}/participants/{playerId}/undo_check_in.json', [TournamentApiController::class, 'undoCheckInPlayer']);

    // Seeding
    Route::get('/tournaments/{openId}.json', [TournamentApiController::class, 'seedingStateMatches']);

    Route::post('/tournaments/{openId}/process_check_ins.json', [TournamentApiController::class, 'processCheckedInPlayers']);
    Route::post('/tournaments/{openId}/start.json', [TournamentApiController::class, 'startTournament']);
    Route::post('/tournaments/{openId}/finalize.json', [TournamentApiController::class, 'endTournament']);

    Route::post('/tournaments/{openId}/import/players/{playerCount}', [TournamentApiController::class, 'importPlayers']);

    Route::get('/tournaments/{openId}/matches.json', [TournamentApiController::class, 'getMatches']);
    Route::put('/tournaments/{openId}/matches/{matchId}.json', [TournamentApiController::class, 'updateMatchScores']);
});

Route::get('/test', function () {
    $service = new TournamentMatchService();

    return $service->generateMatches(1);
});
