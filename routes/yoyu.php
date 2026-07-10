<?php

use App\Http\Controllers\Yoyu\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('yoyu', [HomeController::class, 'index'])->name('yoyu.home');
});
