<?php

declare(strict_types=1);

use App\Modules\Cycles\Http\Controllers\CycleDetailController;
use App\Modules\Cycles\Http\Controllers\CycleListController;
use App\Modules\Cycles\Http\Controllers\CycleResourceController;
use App\Modules\Cycles\Http\Controllers\CycleWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('cycles', [CycleListController::class, 'index'])->name('cycles.index');
    Route::post('cycles', [CycleWriteController::class, 'store'])->name('cycles.store');
    Route::get('cycles/{number}', [CycleDetailController::class, 'show'])
        ->where('number', '\d+')
        ->name('cycles.show');
    Route::patch('cycles/{number}', [CycleWriteController::class, 'update'])
        ->where('number', '\d+')
        ->name('cycles.update');
    Route::post('cycles/{number}/resources', [CycleResourceController::class, 'store'])
        ->where('number', '\d+')
        ->name('cycles.resources.store');
    Route::delete('cycles/{number}/resources/{id}', [CycleResourceController::class, 'destroy'])
        ->where(['number' => '\d+', 'id' => '\d+'])
        ->name('cycles.resources.destroy');
});
