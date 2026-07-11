<?php

use App\Domain\Kioku\Models\Memory;
use App\Http\Controllers\Kioku\MemoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('kioku')->name('kioku.')->group(function () {
    Route::get('/', [MemoryController::class, 'index'])->name('home');
    Route::post('/memories', [MemoryController::class, 'store'])->name('memories.store');
    Route::get('/memories/status', [MemoryController::class, 'status'])
        ->middleware('throttle:60,1')
        ->name('memories.status');
    Route::get('/memories/{memory}', [MemoryController::class, 'show'])->name('memories.show');
    Route::post('/memories/{memory}/reenrich', [MemoryController::class, 'reenrich'])->name('memories.reenrich');
    Route::get('/sources', function () {
        $counts = Memory::query()
            ->where('user_id', auth()->id())
            ->get(['source_type'])
            ->countBy('source_type')
            ->all();

        return inertia('Kioku/Sources', [
            'sourceCounts' => $counts,
        ]);
    })->name('sources');
    Route::get('/settings', fn () => inertia('Kioku/Settings'))->name('settings');
});
