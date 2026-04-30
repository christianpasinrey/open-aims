<?php

declare(strict_types=1);

use App\Modules\Cycles\Http\Controllers\CycleDetailController;
use App\Modules\Cycles\Http\Controllers\CycleListController;
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
});
