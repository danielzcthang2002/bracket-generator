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

    Route::get('tournaments/create', [TournamentController::class, 'create'])->name('tournament.create');
    Route::post('tournaments', [TournamentController::class, 'store'])->name('tournament.store');
    Route::get('tournaments', [TournamentController::class, 'index'])->name('tournament.index');
    Route::get('tournaments/{openId}/edit', [TournamentController::class, 'edit'])->name('tournament.edit');
    Route::put('tournaments/{openId}', [TournamentController::class, 'update'])->name('tournament.update');
    Route::get('tournaments/{openId}', [TournamentController::class, 'show'])->name('tournament.show');
    Route::post('tournaments/{openId}/players', [TournamentController::class, 'storePlayer'])->name('tournament.player.store');
    Route::put('tournaments/{openId}/players/{playerId}', [TournamentController::class, 'updatePlayer'])->name('tournament.player.update');
    Route::delete('tournaments/{openId}/players/{playerId}', [TournamentController::class, 'destroyPlayer'])->name('tournament.player.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
