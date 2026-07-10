<?php

use App\Http\Controllers\Yoyu\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('yoyu')->name('yoyu.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::post('/tasks', [HomeController::class, 'storeTask'])->name('tasks.store');
    Route::patch('/tasks/{task}', [HomeController::class, 'updateTask'])->name('tasks.update');
    Route::delete('/tasks/{task}', [HomeController::class, 'destroyTask'])->name('tasks.destroy');
    Route::post('/focus', [HomeController::class, 'storeFocus'])->name('focus.store');
    Route::patch('/focus/{focus}', [HomeController::class, 'updateFocus'])->name('focus.update');
    Route::post('/briefing', [HomeController::class, 'regenerateBriefing'])->name('briefing.regenerate');
    Route::post('/chat', [HomeController::class, 'chat'])->name('chat');
});
