<?php

use App\Http\Controllers\Api\TournamentApiController;
use App\Services\TournamentMatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/tournaments.json', [TournamentApiController::class, 'index']);
// Route::get('/{id}', [TournamentApiController::class, 'show']);

// POST and PUT methods for creating and updating tournaments
Route::post('/tournaments.json', [TournamentApiController::class, 'store']);
// Route::put('/{id}', [TournamentApiController::class, 'update']);

// Route::get('/{tournamentId}/players', [TournamentApiController::class, 'getPlayers']);
Route::post('/tournaments/{tournamentId}/participants.json', [TournamentApiController::class, 'addPlayer']);
Route::get('/tournaments/{tournamentId}/participants.json', [TournamentApiController::class, 'getParticipants']);


Route::delete('/tournaments/{tournamentId}/participants/{playerId}.json', [TournamentApiController::class, 'removePlayer']);

Route::post('/tournaments/{tournamentId}/participants/{playerId}/check_in.json', [TournamentApiController::class, 'checkInPlayer']);
Route::post('/tournaments/{tournamentId}/participants/{playerId}/undo_check_in.json', [TournamentApiController::class, 'undoCheckInPlayer']);

Route::get('/tournaments/{tournamentId}.json', [TournamentApiController::class, 'seedingStateMatches']);

Route::post('/tournaments/{tournamentId}/process_check_ins.json', [TournamentApiController::class, 'processCheckedInPlayers']);
Route::post('/tournaments/{tournamentId}/start.json', [TournamentApiController::class, 'startTournament']);
Route::post('/tournaments/{tournamentId}/finalize.json', [TournamentApiController::class, 'endTournament']);

Route::post('/tournaments/{tournamentId}/import/players/{playerCount}', [TournamentApiController::class, 'importPlayers']);

Route::get('/tournaments/{tournamentId}/matches.json', [TournamentApiController::class, 'getMatches']);
Route::put('/tournaments/{tournamentId}/matches/{matchId}.json', [TournamentApiController::class, 'updateMatchScores']);

Route::get('/test', function () {
    $service = new TournamentMatchService();

    return $service->generateMatches(1);
});
