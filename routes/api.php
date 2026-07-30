<?php

use App\Http\Controllers\Api\Kioku\IosCaptureController;
use App\Http\Middleware\AuthenticateKiokuCaptureToken;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateKiokuCaptureToken::class, 'throttle:30,1'])
    ->prefix('kioku')
    ->group(function (): void {
        Route::post('/captures', [IosCaptureController::class, 'store'])
            ->name('api.kioku.captures.store');
    });
