<?php

use App\Http\Controllers\DailyCheckinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoodBarcodeLookupController;
use App\Http\Controllers\FoodItemController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\GoalMetricController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LifeAreaController;
use App\Http\Controllers\MatrixCellItemController;
use App\Http\Controllers\MealEntryController;
use App\Http\Controllers\MetricRecordController;
use App\Http\Controllers\NutritionGoalController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgramPlanController;
use App\Http\Controllers\RecommendationDecisionController;
use App\Http\Controllers\RoutineBlockLogController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\RoutineItemController;
use App\Http\Controllers\RoutinePlanController;
use App\Http\Controllers\RoutinePlanStepController;
use App\Http\Controllers\RoutineSessionController;
use App\Http\Controllers\RoutineSessionStepController;
use App\Http\Controllers\RoutineStepController;
use App\Http\Controllers\SymptomObservationController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\VideoController;
use App\Models\Metric;
use App\Models\RoutineBlockLog;
use App\Models\RoutineItem;
use App\Models\RoutinePlan;
use App\Models\RoutineSession;
use App\Models\RoutineSessionStep;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::bind('metric', function (string $value): Metric {
    return Metric::query()
        ->where('key', $value)
        ->where(function ($query): void {
            $query->whereNull('user_id')->orWhere('user_id', Auth::id());
        })
        ->orderByRaw('user_id is null')
        ->firstOrFail();
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
        'canResetPassword' => config('app.public_signup_enabled') && Features::enabled(Features::resetPasswords()),
        'canRegister' => config('app.public_signup_enabled') && Features::enabled(Features::registration()),
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

    Route::get('goals', [GoalController::class, 'index'])->name('goals.index');
    Route::post('goals', [GoalController::class, 'store'])->name('goals.store');
    Route::get('goals/{goal}', [GoalController::class, 'show'])->name('goals.show');
    Route::patch('goals/{goal}', [GoalController::class, 'update'])->name('goals.update');
    Route::delete('goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy');
    Route::post('goals/{goal}/metrics', [GoalMetricController::class, 'store'])->name('goal-metrics.store');
    Route::patch('goal-metrics/{goalMetric}', [GoalMetricController::class, 'update'])->name('goal-metrics.update');
    Route::delete('goal-metrics/{goalMetric}', [GoalMetricController::class, 'destroy'])->name('goal-metrics.destroy');

    Route::get('programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::get('programs/{program}', [ProgramController::class, 'show'])->name('programs.show');
    Route::get('programs/{program}/roadmap', [ProgramController::class, 'roadmap'])->name('programs.roadmap');
    Route::post('programs/{program}/versions', [ProgramController::class, 'revise'])->name('programs.versions.store');

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
    Route::put('today/checkin', [DailyCheckinController::class, 'upsert'])->name('today.checkin.upsert');
    Route::post('today/symptoms', [SymptomObservationController::class, 'store'])->name('today.symptoms.store');
    Route::post('today/program-choice', [ProgramPlanController::class, 'selectChoice'])->name('today.program-choice');
    Route::post('recommendations/{recommendation}/decisions', [RecommendationDecisionController::class, 'store'])
        ->name('recommendations.decisions.store');
    Route::post('plans', [RoutinePlanController::class, 'store'])->name('routine-plans.store');
    Route::get('plans/{p}', [RoutinePlanController::class, 'show'])->name('routine-plans.show');
    Route::patch('plans/{p}', [RoutinePlanController::class, 'update'])->name('routine-plans.update');
    Route::post('plans/{p}/today-adjust', [ProgramPlanController::class, 'todayAdjust'])->name('routine-plans.today-adjust');
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
    Route::get('records/condition', [MetricRecordController::class, 'condition'])->name('records.condition');
    Route::get('records/strength', [MetricRecordController::class, 'strength'])->name('records.strength');
    Route::put('records/daily', [MetricRecordController::class, 'upsertDaily'])->name('records.upsert-daily');
    Route::get('records/{metric}', [MetricRecordController::class, 'show'])->name('records.show');
    Route::delete('records/{metric}/{metricRecord}', [MetricRecordController::class, 'destroy'])->name('records.destroy');

    Route::get('meals', [MealEntryController::class, 'index'])->name('meals.index');
    Route::post('meals', [MealEntryController::class, 'store'])->name('meals.store');
    Route::post('meals/copy-previous-day', [MealEntryController::class, 'copyPreviousDay'])
        ->name('meals.copy-previous-day');
    Route::get('meals/foods', [FoodItemController::class, 'index'])->name('meals.foods.index');
    Route::post('meals/foods', [FoodItemController::class, 'store'])->name('meals.foods.store');
    Route::patch('meals/foods/{foodItem}', [FoodItemController::class, 'update'])->name('meals.foods.update');
    Route::delete('meals/foods/{foodItem}', [FoodItemController::class, 'destroy'])->name('meals.foods.destroy');
    Route::post('meals/barcode-lookup', [FoodBarcodeLookupController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('meals.barcode-lookup.store');
    Route::get('meals/barcode-lookup/{lookupId}', [FoodBarcodeLookupController::class, 'show'])->name('meals.barcode-lookup.show');
    Route::post('meals/barcode-lookup/{lookupId}/label-image', [FoodBarcodeLookupController::class, 'storeLabelImage'])
        ->middleware('throttle:10,1')
        ->name('meals.barcode-lookup.label-image.store');
    Route::post('meals/label-ocr', [FoodBarcodeLookupController::class, 'storeLabelOcr'])
        ->middleware('throttle:10,1')
        ->name('meals.label-ocr.store');
    Route::post('meals/barcode-lookup/{lookupId}/confirm', [FoodBarcodeLookupController::class, 'confirm'])->name('meals.barcode-lookup.confirm');
    Route::put('meals/goals', [NutritionGoalController::class, 'upsert'])->name('meals.goals.upsert');
    Route::patch('meals/{mealEntry}', [MealEntryController::class, 'update'])->name('meals.update');
    Route::delete('meals/{mealEntry}', [MealEntryController::class, 'destroy'])->name('meals.destroy');
});

Route::redirect('/finance', '/yoyu/money')->middleware(['auth', 'verified']);

require __DIR__.'/settings.php';
require __DIR__.'/yoyu.php';
require __DIR__.'/kioku.php';
