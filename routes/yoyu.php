<?php

use App\Http\Controllers\Yoyu\CalendarConnectionController;
use App\Http\Controllers\Yoyu\HomeController;
use App\Http\Controllers\Yoyu\Money\MoneyAccountController;
use App\Http\Controllers\Yoyu\Money\MoneyAnalysisController;
use App\Http\Controllers\Yoyu\Money\MoneyCardController;
use App\Http\Controllers\Yoyu\Money\MoneyCashflowController;
use App\Http\Controllers\Yoyu\Money\MoneyDashboardController;
use App\Http\Controllers\Yoyu\Money\MoneyDecisionController;
use App\Http\Controllers\Yoyu\Money\MoneyExportController;
use App\Http\Controllers\Yoyu\Money\MoneyImportController;
use App\Http\Controllers\Yoyu\Money\MoneyLoanController;
use App\Http\Controllers\Yoyu\Money\MoneyMonthlySnapshotController;
use App\Http\Controllers\Yoyu\Money\MoneySettingsController;
use App\Http\Controllers\Yoyu\Money\MoneySimulationController;
use App\Http\Controllers\Yoyu\Money\MoneyTransactionController;
use App\Http\Controllers\Yoyu\PlaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('yoyu')->name('yoyu.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::post('/tasks', [HomeController::class, 'storeTask'])->name('tasks.store');
    Route::patch('/tasks/{task}', [HomeController::class, 'updateTask'])->name('tasks.update');
    Route::delete('/tasks/{task}', [HomeController::class, 'destroyTask'])->name('tasks.destroy');
    Route::post('/places', [PlaceController::class, 'upsert'])->name('places.upsert');
    Route::patch('/events/travel-lead', [PlaceController::class, 'updateEventTravelLead'])
        ->name('events.travel-lead');
    Route::post('/focus', [HomeController::class, 'storeFocus'])->name('focus.store');
    Route::patch('/focus/{focus}', [HomeController::class, 'updateFocus'])->name('focus.update');
    Route::post('/briefing', [HomeController::class, 'regenerateBriefing'])->name('briefing.regenerate');
    Route::post('/chat', [HomeController::class, 'chat'])->name('chat');

    Route::get('/settings', [CalendarConnectionController::class, 'settings'])->name('settings');
    Route::patch('/settings/travel-lead', [CalendarConnectionController::class, 'updateTravelLead'])
        ->name('settings.travel-lead');
    Route::get('/settings/calendar/connect', [CalendarConnectionController::class, 'connect'])
        ->middleware('throttle:10,1')
        ->name('calendar.connect');
    Route::get('/settings/calendar/callback', [CalendarConnectionController::class, 'callback'])
        ->name('calendar.callback');
    Route::post('/settings/calendar/sync', [CalendarConnectionController::class, 'sync'])
        ->middleware('throttle:6,1')
        ->name('calendar.sync');
    Route::delete('/settings/calendar', [CalendarConnectionController::class, 'disconnect'])
        ->name('calendar.disconnect');

    Route::prefix('money')->name('money.')->group(function () {
        Route::get('/', [MoneyDashboardController::class, 'index'])->name('dashboard');

        Route::get('cashflows', [MoneyCashflowController::class, 'index'])->name('cashflows.index');
        Route::post('cashflows', [MoneyCashflowController::class, 'store'])->name('cashflows.store');
        Route::post('cashflows/{cashflow}/settle', [MoneyCashflowController::class, 'settle'])->name('cashflows.settle');
        Route::post('cashflows/{cashflow}/defer', [MoneyCashflowController::class, 'defer'])->name('cashflows.defer');
        Route::delete('cashflows/{cashflow}', [MoneyCashflowController::class, 'destroy'])->name('cashflows.destroy');

        Route::get('accounts', [MoneyAccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts', [MoneyAccountController::class, 'store'])->name('accounts.store');
        Route::patch('accounts/{account}/balance', [MoneyAccountController::class, 'updateBalance'])->name('accounts.balance');
        Route::patch('accounts/{account}/toggle', [MoneyAccountController::class, 'toggle'])->name('accounts.toggle');

        Route::get('cards', [MoneyCardController::class, 'index'])->name('cards.index');
        Route::post('cards', [MoneyCardController::class, 'store'])->name('cards.store');
        Route::patch('cards/{card}/snapshot', [MoneyCardController::class, 'updateSnapshot'])->name('cards.snapshot');
        Route::post('cards/{card}/statements', [MoneyCardController::class, 'storeStatement'])->name('cards.statements.store');

        Route::get('loans', [MoneyLoanController::class, 'index'])->name('loans.index');
        Route::post('loans', [MoneyLoanController::class, 'store'])->name('loans.store');
        Route::patch('loans/{loan}/balance', [MoneyLoanController::class, 'updateBalance'])->name('loans.balance');
        Route::post('loans/{loan}/payments', [MoneyLoanController::class, 'storePayment'])->name('loans.payments.store');

        Route::get('transactions', [MoneyTransactionController::class, 'index'])->name('transactions.index');
        Route::post('transactions', [MoneyTransactionController::class, 'store'])->name('transactions.store');
        Route::post('transactions/{transaction}/void', [MoneyTransactionController::class, 'void'])->name('transactions.void');

        Route::get('analysis', [MoneyAnalysisController::class, 'index'])->name('analysis.index');

        Route::get('imports', [MoneyImportController::class, 'index'])->name('imports.index');
        Route::get('imports/create', [MoneyImportController::class, 'create'])->name('imports.create');
        Route::post('imports', [MoneyImportController::class, 'store'])->name('imports.store');
        Route::post('imports/{import}/configure', [MoneyImportController::class, 'configure'])->name('imports.configure');
        Route::post('imports/{import}/execute', [MoneyImportController::class, 'execute'])->name('imports.execute');
        Route::post('imports/{import}/rollback', [MoneyImportController::class, 'rollback'])->name('imports.rollback');

        Route::get('simulations', [MoneySimulationController::class, 'index'])->name('simulations.index');
        Route::post('simulations', [MoneySimulationController::class, 'store'])->name('simulations.store');
        Route::post('simulations/{simulation}/actions', [MoneySimulationController::class, 'storeAction'])->name('simulations.actions.store');
        Route::post('simulations/{simulation}/calculate', [MoneySimulationController::class, 'calculate'])->name('simulations.calculate');
        Route::post('simulations/{simulation}/apply', [MoneySimulationController::class, 'apply'])->name('simulations.apply');
        Route::post('simulations/{simulation}/discard', [MoneySimulationController::class, 'discard'])->name('simulations.discard');

        Route::get('decisions', [MoneyDecisionController::class, 'index'])->name('decisions.index');
        Route::post('decisions', [MoneyDecisionController::class, 'store'])->name('decisions.store');
        Route::patch('decisions/{decision}/review', [MoneyDecisionController::class, 'review'])->name('decisions.review');

        Route::get('settings', [MoneySettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [MoneySettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/setup', [MoneySettingsController::class, 'setup'])->name('settings.setup');

        Route::post('months/{month}/close', [MoneyMonthlySnapshotController::class, 'close'])
            ->where('month', '\d{4}-\d{2}')
            ->name('months.close');

        Route::get('export', [MoneyExportController::class, 'download'])->name('export');
    });
});
