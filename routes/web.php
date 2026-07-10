<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LifeAreaController;
use App\Http\Controllers\MatrixCellItemController;
use App\Http\Controllers\MetricRecordController;
use App\Http\Controllers\RoutineBlockLogController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\RoutineItemController;
use App\Http\Controllers\RoutinePlanController;
use App\Http\Controllers\RoutinePlanStepController;
use App\Http\Controllers\RoutineSessionController;
use App\Http\Controllers\RoutineSessionStepController;
use App\Http\Controllers\RoutineStepController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\VideoController;
use App\Models\Metric;
use App\Models\RoutineBlockLog;
use App\Models\RoutineItem;
use App\Models\RoutinePlan;
use App\Models\RoutineSession;
use App\Models\RoutineSessionStep;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::bind('metric', function (string $value): Metric {
    return Metric::query()->where('key', $value)->firstOrFail();
});

Route::bind('item', function (string $value): RoutineItem {
    return RoutineItem::query()->whereKey($value)->firstOrFail();
});

Route::bind('p', function (string $value): RoutinePlan {
    return RoutinePlan::query()->whereKey($value)->firstOrFail();
});

Route::bind('s', function (string $value): RoutineSession {
    return RoutineSession::query()->whereKey($value)->firstOrFail();
});

Route::bind('ss', function (string $value): RoutineSessionStep {
    return RoutineSessionStep::query()->whereKey($value)->firstOrFail();
});

Route::bind('bl', function (string $value): RoutineBlockLog {
    return RoutineBlockLog::query()->whereKey($value)->firstOrFail();
});

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('PublicLandingPage', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'passwordRules' => Password::defaults()->toPasswordRulesString(),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('life-areas', [LifeAreaController::class, 'index'])->name('life-areas.index');
    Route::post('life-areas', [LifeAreaController::class, 'store'])->name('life-areas.store');
    Route::patch('life-areas/reorder', [LifeAreaController::class, 'reorder'])->name('life-areas.reorder');
    Route::patch('life-areas/{lifeArea}', [LifeAreaController::class, 'update'])->name('life-areas.update');
    Route::delete('life-areas/{lifeArea}', [LifeAreaController::class, 'destroy'])->name('life-areas.destroy');
    Route::patch('life-areas/{lifeArea}/restore', [LifeAreaController::class, 'restore'])->name('life-areas.restore');

    Route::post('matrix-cells/{matrixCell}/items', [MatrixCellItemController::class, 'store'])->name('matrix-cell-items.store');
    Route::patch('matrix-cell-items/{matrixCellItem}', [MatrixCellItemController::class, 'update'])->name('matrix-cell-items.update');
    Route::patch('matrix-cell-items/{matrixCellItem}/toggle', [MatrixCellItemController::class, 'toggle'])->name('matrix-cell-items.toggle');
    Route::delete('matrix-cell-items/{matrixCellItem}', [MatrixCellItemController::class, 'destroy'])->name('matrix-cell-items.destroy');

    Route::post('videos/upload-url', [VideoController::class, 'createUploadUrl'])
        ->middleware('throttle:10,1')
        ->name('videos.upload-url');
    Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
    Route::post('videos/{video}/upload-url', [VideoController::class, 'refreshUploadUrl'])->name('videos.refresh-upload-url');
    Route::post('videos/{video}/finalize', [VideoController::class, 'finalize'])->name('videos.finalize');
    Route::get('videos/{video}/stream-url', [VideoController::class, 'streamUrl'])->name('videos.stream-url');
    Route::patch('videos/{video}', [VideoController::class, 'update'])->name('videos.update');
    Route::delete('videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');

    Route::get('routine-items', [RoutineItemController::class, 'index'])->name('routine-items.index');
    Route::get('routine-items/{item}', [RoutineItemController::class, 'show'])->name('routine-items.show');
    Route::post('routine-items', [RoutineItemController::class, 'store'])->name('routine-items.store');
    Route::patch('routine-items/{item}', [RoutineItemController::class, 'update'])->name('routine-items.update');
    Route::delete('routine-items/{item}', [RoutineItemController::class, 'destroy'])->name('routine-items.destroy');

    Route::get('routines', [RoutineController::class, 'index'])->name('routines.index');
    Route::get('routines/create', [RoutineController::class, 'create'])->name('routines.create');
    Route::post('routines', [RoutineController::class, 'store'])->name('routines.store');
    Route::get('routines/{routine}', [RoutineController::class, 'show'])->name('routines.show');
    Route::patch('routines/{routine}', [RoutineController::class, 'update'])->name('routines.update');
    Route::delete('routines/{routine}', [RoutineController::class, 'destroy'])->name('routines.destroy');
    Route::post('routines/{routine}/steps', [RoutineStepController::class, 'store'])->name('routine-steps.store');
    Route::patch('routines/{routine}/steps/reorder', [RoutineStepController::class, 'reorder'])->name('routine-steps.reorder');
    Route::patch('routines/{routine}/steps/{routineStep}', [RoutineStepController::class, 'update'])->name('routine-steps.update');
    Route::delete('routines/{routine}/steps/{routineStep}', [RoutineStepController::class, 'destroy'])->name('routine-steps.destroy');

    Route::get('today', [TodayController::class, 'index'])->name('today.index');
    Route::post('plans', [RoutinePlanController::class, 'store'])->name('routine-plans.store');
    Route::get('plans/{p}', [RoutinePlanController::class, 'show'])->name('routine-plans.show');
    Route::patch('plans/{p}', [RoutinePlanController::class, 'update'])->name('routine-plans.update');
    Route::delete('plans/{p}', [RoutinePlanController::class, 'destroy'])->name('routine-plans.destroy');
    Route::post('plans/{p}/steps', [RoutinePlanStepController::class, 'store'])->name('routine-plan-steps.store');
    Route::patch('plans/{p}/steps/reorder', [RoutinePlanStepController::class, 'reorder'])->name('routine-plan-steps.reorder');
    Route::patch('plans/{p}/steps/{step}', [RoutinePlanStepController::class, 'update'])->name('routine-plan-steps.update');
    Route::delete('plans/{p}/steps/{step}', [RoutinePlanStepController::class, 'destroy'])->name('routine-plan-steps.destroy');

    Route::post('plans/{p}/sessions', [RoutineSessionController::class, 'start'])->name('routine-sessions.start');
    Route::get('sessions/{s}', [RoutineSessionController::class, 'show'])->name('routine-sessions.show');
    Route::post('sessions/{s}/complete', [RoutineSessionController::class, 'complete'])->name('routine-sessions.complete');
    Route::post('sessions/{s}/abort', [RoutineSessionController::class, 'abort'])->name('routine-sessions.abort');
    Route::patch('sessions/{s}/steps/{ss}', [RoutineSessionStepController::class, 'update'])->name('routine-session-steps.update');
    Route::post('session-steps/{ss}/blocks', [RoutineBlockLogController::class, 'store'])->name('routine-block-logs.store');
    Route::patch('blocks/{bl}', [RoutineBlockLogController::class, 'update'])->name('routine-block-logs.update');
    Route::delete('blocks/{bl}', [RoutineBlockLogController::class, 'destroy'])->name('routine-block-logs.destroy');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');

    Route::get('records', [MetricRecordController::class, 'index'])->name('records.index');
    Route::put('records/daily', [MetricRecordController::class, 'upsertDaily'])->name('records.upsert-daily');
    Route::get('records/{metric}', [MetricRecordController::class, 'show'])->name('records.show');
    Route::delete('records/{metric}/{metricRecord}', [MetricRecordController::class, 'destroy'])->name('records.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/yoyu.php';
require __DIR__.'/kioku.php';
