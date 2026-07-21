<?php

use App\Domain\Kioku\Models\Memory;
use App\Http\Controllers\Kioku\CaptureController;
use App\Http\Controllers\Kioku\LetterController;
use App\Http\Controllers\Kioku\MemoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('kioku')->name('kioku.')->group(function () {
    Route::get('/', [MemoryController::class, 'index'])->name('home');
    Route::get('/memories', [MemoryController::class, 'library'])->name('memories.index');
    Route::post('/memories', [MemoryController::class, 'store'])->name('memories.store');
    Route::post('/captures/manual', [CaptureController::class, 'manual'])
        ->middleware('throttle:60,1')
        ->name('captures.manual');
    Route::post('/captures/voice', [CaptureController::class, 'voice'])
        ->middleware('throttle:60,1')
        ->name('captures.voice');
    Route::post('/captures/events', [CaptureController::class, 'event'])
        ->middleware('throttle:120,1')
        ->name('captures.events');
    Route::get('/memories/status', [MemoryController::class, 'status'])
        ->middleware('throttle:60,1')
        ->name('memories.status');
    Route::get('/memories/{memory}', [MemoryController::class, 'show'])->name('memories.show');
    Route::get('/memories/{memory}/audio', [MemoryController::class, 'audio'])->name('memories.audio');
    Route::post('/memories/{memory}/reenrich', [MemoryController::class, 'reenrich'])->name('memories.reenrich');
    Route::post('/memories/{memory}/retry-transcription', [MemoryController::class, 'retryTranscription'])->name('memories.retry-transcription');
    Route::put('/memories/{memory}/tags', [MemoryController::class, 'updateTags'])->name('memories.tags.update');
    Route::get('/letters', [LetterController::class, 'index'])->name('letters.index');
    Route::get('/letters/preview', [LetterController::class, 'preview'])->name('letters.preview');
    Route::get('/letters/{letter}', [LetterController::class, 'show'])->name('letters.show');
    Route::post('/letters/{letter}/open', [LetterController::class, 'open'])->name('letters.open');
    // {letterItem}, not {item}: web.php globally binds {item} to RoutineItem.
    Route::put('/letters/{letter}/items/{letterItem}/verdict', [LetterController::class, 'storeVerdict'])->name('letters.items.verdict');
    Route::post('/letters/{letter}/complete', [LetterController::class, 'complete'])->name('letters.complete');
    Route::delete('/letters/{letter}', [LetterController::class, 'destroy'])->name('letters.destroy');
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
