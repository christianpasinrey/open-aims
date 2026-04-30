<?php

declare(strict_types=1);

use App\Modules\Cycles\Http\Controllers\CycleListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('cycles', [CycleListController::class, 'index'])->name('cycles.index');
});
