<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LifeAreaController;
use App\Http\Controllers\MatrixCellItemController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('life-areas', [LifeAreaController::class, 'index'])->name('life-areas.index');
    Route::post('life-areas', [LifeAreaController::class, 'store'])->name('life-areas.store');
    // reorder は {lifeArea} バインディングより先に定義する（"reorder" が ID と解釈されないように）
    Route::patch('life-areas/reorder', [LifeAreaController::class, 'reorder'])->name('life-areas.reorder');
    Route::patch('life-areas/{lifeArea}', [LifeAreaController::class, 'update'])->name('life-areas.update');
    Route::delete('life-areas/{lifeArea}', [LifeAreaController::class, 'destroy'])->name('life-areas.destroy');
    Route::patch('life-areas/{lifeArea}/restore', [LifeAreaController::class, 'restore'])->name('life-areas.restore');

    Route::post('matrix-cells/{matrixCell}/items', [MatrixCellItemController::class, 'store'])->name('matrix-cell-items.store');
    Route::patch('matrix-cell-items/{matrixCellItem}', [MatrixCellItemController::class, 'update'])->name('matrix-cell-items.update');
    Route::patch('matrix-cell-items/{matrixCellItem}/toggle', [MatrixCellItemController::class, 'toggle'])->name('matrix-cell-items.toggle');
    Route::delete('matrix-cell-items/{matrixCellItem}', [MatrixCellItemController::class, 'destroy'])->name('matrix-cell-items.destroy');
});

require __DIR__.'/settings.php';
