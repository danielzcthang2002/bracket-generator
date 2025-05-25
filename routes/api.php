<?php

use App\Http\Controllers\Api\TournamentApiController;
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

    Route::group(['prefix' => '/{tournamentId}/players'], function () {
    Route::get('/', [TournamentApiController::class, 'getPlayers']);
    Route::post('/', [TournamentApiController::class, 'addPlayer']);
    // Route::put('/{playerId}', [TournamentApiController::class, 'updatePlayer']);
    Route::delete('/{playerId}', [TournamentApiController::class, 'removePlayer']);
});
});


