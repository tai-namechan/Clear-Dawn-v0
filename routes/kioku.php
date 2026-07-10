<?php

use App\Http\Controllers\Kioku\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('kioku', [HomeController::class, 'index'])->name('kioku.home');
});
