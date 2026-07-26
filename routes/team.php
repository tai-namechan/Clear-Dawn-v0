<?php

use App\Http\Controllers\Team\TeamAthleteController;
use App\Http\Controllers\Team\TeamAuthController;
use App\Http\Controllers\Team\TeamProgramController;
use App\Http\Controllers\Team\TeamReportController;
use App\Http\Controllers\Team\TeamSettingsController;
use App\Http\Controllers\Team\TeamWorkspaceController;
use App\Models\TeamUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::domain(config('app.team_domain'))->name('team.')->group(function (): void {
    Route::get('/login', [TeamAuthController::class, 'create'])->name('login');
    Route::get('/auth/google', [TeamAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('auth.google');
    Route::get('/auth/google/callback', [TeamAuthController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');

    if (app()->isLocal()) {
        Route::post('/demo-login', function (Request $request) {
            $teamUser = TeamUser::query()->where('email', 'coach@team.local')->where('status', 'active')->firstOrFail();
            Auth::guard('team')->login($teamUser);
            $request->session()->regenerate();

            return redirect()->route('team.home');
        })->middleware('throttle:10,1')->name('demo.login');
    }

    Route::middleware('team.auth')->group(function (): void {
        Route::get('/', [TeamWorkspaceController::class, 'home'])->name('home');
        Route::post('/logout', [TeamAuthController::class, 'destroy'])->name('logout');
        Route::get('/t/{team}/dashboard', [TeamWorkspaceController::class, 'dashboard'])->name('dashboard');
        Route::get('/t/{team}/athletes', [TeamWorkspaceController::class, 'athletes'])->name('athletes.index');
        Route::get('/t/{team}/athletes/{athlete}', [TeamAthleteController::class, 'show'])->name('athletes.show');
        Route::get('/t/{team}/athletes/{athlete}/training', [TeamAthleteController::class, 'training'])->name('athletes.training');
        Route::get('/t/{team}/athletes/{athlete}/meals', [TeamAthleteController::class, 'meals'])->name('athletes.meals');
        Route::get('/t/{team}/athletes/{athlete}/condition', [TeamAthleteController::class, 'condition'])->name('athletes.condition');
        Route::get('/t/{team}/athletes/{athlete}/goals', [TeamAthleteController::class, 'goals'])->name('athletes.goals');
        Route::get('/t/{team}/athletes/{athlete}/report', [TeamAthleteController::class, 'report'])->name('athletes.report');
        Route::get('/t/{team}/programs', [TeamProgramController::class, 'index'])->name('programs.index');
        Route::get('/t/{team}/reports', [TeamReportController::class, 'show'])->name('reports.show');
        Route::get('/t/{team}/settings', [TeamSettingsController::class, 'show'])->name('settings.show');
    });
});
