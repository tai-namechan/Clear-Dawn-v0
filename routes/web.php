<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LifeAreaController;
use App\Http\Controllers\MatrixCellItemController;
use App\Http\Controllers\MetricRecordController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\RoutineStepController;
use App\Http\Controllers\TrainingPlanController;
use App\Http\Controllers\TrainingPlanStepController;
use App\Http\Controllers\TrainingRunController;
use App\Http\Controllers\TrainingRunStepController;
use App\Http\Controllers\TrainingSetLogController;
use App\Http\Controllers\VideoController;
use App\Models\Metric;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::bind('metric', function (string $value): Metric {
    return Metric::query()->where('key', $value)->firstOrFail();
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

    Route::get('exercises', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::post('exercises', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::patch('exercises/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');

    Route::get('routines', [RoutineController::class, 'index'])->name('routines.index');
    Route::post('routines', [RoutineController::class, 'store'])->name('routines.store');
    Route::get('routines/{routine}', [RoutineController::class, 'show'])->name('routines.show');
    Route::patch('routines/{routine}', [RoutineController::class, 'update'])->name('routines.update');
    Route::delete('routines/{routine}', [RoutineController::class, 'destroy'])->name('routines.destroy');
    Route::post('routines/{routine}/steps', [RoutineStepController::class, 'store'])->name('routine-steps.store');
    Route::patch('routines/{routine}/steps/reorder', [RoutineStepController::class, 'reorder'])->name('routine-steps.reorder');
    Route::patch('routines/{routine}/steps/{routineStep}', [RoutineStepController::class, 'update'])->name('routine-steps.update');
    Route::delete('routines/{routine}/steps/{routineStep}', [RoutineStepController::class, 'destroy'])->name('routine-steps.destroy');

    Route::get('training', [TrainingPlanController::class, 'index'])->name('training.index');
    Route::post('training/plans', [TrainingPlanController::class, 'store'])->name('training-plans.store');
    Route::get('training/plans/{trainingPlan}', [TrainingPlanController::class, 'show'])->name('training-plans.show');
    Route::patch('training/plans/{trainingPlan}', [TrainingPlanController::class, 'update'])->name('training-plans.update');
    Route::delete('training/plans/{trainingPlan}', [TrainingPlanController::class, 'destroy'])->name('training-plans.destroy');
    Route::post('training/plans/{trainingPlan}/steps', [TrainingPlanStepController::class, 'store'])->name('training-plan-steps.store');
    Route::patch('training/plans/{trainingPlan}/steps/reorder', [TrainingPlanStepController::class, 'reorder'])->name('training-plan-steps.reorder');
    Route::patch('training/plans/{trainingPlan}/steps/{trainingPlanStep}', [TrainingPlanStepController::class, 'update'])->name('training-plan-steps.update');
    Route::delete('training/plans/{trainingPlan}/steps/{trainingPlanStep}', [TrainingPlanStepController::class, 'destroy'])->name('training-plan-steps.destroy');

    Route::post('training/plans/{trainingPlan}/runs', [TrainingRunController::class, 'start'])->name('training-runs.start');
    Route::get('training/runs/{trainingRun}', [TrainingRunController::class, 'show'])->name('training-runs.show');
    Route::post('training/runs/{trainingRun}/complete', [TrainingRunController::class, 'complete'])->name('training-runs.complete');
    Route::post('training/runs/{trainingRun}/abort', [TrainingRunController::class, 'abort'])->name('training-runs.abort');
    Route::patch('training/runs/{trainingRun}/steps/{trainingRunStep}', [TrainingRunStepController::class, 'update'])->name('training-run-steps.update');
    Route::post('training/run-steps/{trainingRunStep}/sets', [TrainingSetLogController::class, 'store'])->name('training-set-logs.store');
    Route::patch('training/sets/{trainingSetLog}', [TrainingSetLogController::class, 'update'])->name('training-set-logs.update');
    Route::delete('training/sets/{trainingSetLog}', [TrainingSetLogController::class, 'destroy'])->name('training-set-logs.destroy');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');

    Route::get('records', [MetricRecordController::class, 'index'])->name('records.index');
    Route::put('records/daily', [MetricRecordController::class, 'upsertDaily'])->name('records.upsert-daily');
    Route::get('records/{metric}', [MetricRecordController::class, 'show'])->name('records.show');
    Route::delete('records/{metric}/{metricRecord}', [MetricRecordController::class, 'destroy'])->name('records.destroy');
});

require __DIR__.'/settings.php';
