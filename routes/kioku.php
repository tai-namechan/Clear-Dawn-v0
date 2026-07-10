<?php

use App\Http\Controllers\Kioku\MemoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('kioku')->name('kioku.')->group(function () {
    Route::get('/', [MemoryController::class, 'index'])->name('home');
    Route::post('/memories', [MemoryController::class, 'store'])->name('memories.store');
    Route::get('/memories/{memory}', [MemoryController::class, 'show'])->name('memories.show');
    Route::get('/sources', fn () => inertia('Kioku/Sources'))->name('sources');
    Route::get('/settings', fn () => inertia('Kioku/Settings'))->name('settings');
});
