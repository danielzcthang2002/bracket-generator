<?php

use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');



    Route::prefix('tournaments')->group(function () {
        Route::post('/', [TournamentController::class, 'store'])->name('tournament.store');
        Route::get('/', [TournamentController::class, 'index'])->name('tournament.index');
        Route::get('create', [TournamentController::class, 'create'])->name('tournament.create');
        Route::get('{openId}/edit', [TournamentController::class, 'edit'])->name('tournament.edit');
        Route::delete('{openId}', [TournamentController::class, 'destroy'])->name('tournament.destroy');
        Route::put('{openId}', [TournamentController::class, 'update'])->name('tournament.update');
        Route::get('{openId}', [TournamentController::class, 'show'])->name('tournament.show');
        Route::post('{openId}/matches/{matchId}/score', [TournamentController::class, 'updateMatchScore'])->name('tournament.match.score.update');
        Route::post('{openId}/players', [TournamentController::class, 'storePlayer'])->name('tournament.player.store');
        Route::post('{openId}/players/bulk', [TournamentController::class, 'storePlayersBulk'])->name('tournament.player.bulk.store');
        Route::put('{openId}/players/{playerId}', [TournamentController::class, 'updatePlayer'])->name('tournament.player.update');
        Route::delete('{openId}/players/{playerId}', [TournamentController::class, 'destroyPlayer'])->name('tournament.player.destroy');

        // Business Logic
        Route::post('{openId}/start', [TournamentController::class, 'startTournament'])->name('tournament.start');
        Route::post('{openId}/end', [TournamentController::class, 'endTournament'])->name('tournament.end');

    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
