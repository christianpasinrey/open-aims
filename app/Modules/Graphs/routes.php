<?php

declare(strict_types=1);

use App\Modules\Graphs\Http\Controllers\GraphMapController;
use App\Modules\Graphs\Http\Controllers\OwnerGraphsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('graphs/owners/{type}/{id}', [OwnerGraphsController::class, 'show'])
        ->whereIn('type', ['issue', 'project', 'milestone'])
        ->whereNumber('id')
        ->name('graphs.owner');

    Route::get('graphs/map', [GraphMapController::class, 'show'])->name('graphs.map');
});
