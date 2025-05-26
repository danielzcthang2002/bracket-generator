<?php

use App\Http\Controllers\Api\TournamentApiController;
use App\Services\TournamentMatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::group(['prefix' => 'tournaments'], function () {
    Route::get('/', [TournamentApiController::class, 'index']);
    Route::get('/{id}', [TournamentApiController::class, 'show']);

    // POST and PUT methods for creating and updating tournaments
    Route::post('/', [TournamentApiController::class, 'store']);
    Route::put('/{id}', [TournamentApiController::class, 'update']);

    Route::get('/{tournamentId}/players', [TournamentApiController::class, 'getPlayers']);
    Route::post('/{tournamentId}/players', [TournamentApiController::class, 'addPlayer']);
    Route::delete('/{tournamentId}/players/{playerId}', [TournamentApiController::class, 'removePlayer']);
});

Route::get('/test', function(){
    $service = new TournamentMatchService();

    return $service->generateMatches(1);
});
