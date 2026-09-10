<?php

declare(strict_types=1);

use App\Modules\Graphs\Http\Controllers\GraphImpactController;
use App\Modules\Graphs\Http\Controllers\GraphMapController;
use App\Modules\Graphs\Http\Controllers\GraphShowController;
use App\Modules\Graphs\Http\Controllers\MapPageController;
use App\Modules\Graphs\Http\Controllers\OwnerGraphsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('map', [MapPageController::class, 'show'])->name('graphs.map.page');

    Route::get('graphs/owners/{type}/{id}', [OwnerGraphsController::class, 'show'])
        ->whereIn('type', ['issue', 'project', 'milestone'])
        ->whereNumber('id')
        ->name('graphs.owner');

    Route::get('graphs/map', [GraphMapController::class, 'show'])->name('graphs.map');

    Route::get('graphs/{graph}', [GraphShowController::class, 'show'])
        ->whereNumber('graph')
        ->name('graphs.show');

    Route::get('graphs/{graph}/impact', [GraphImpactController::class, 'show'])
        ->whereNumber('graph')
        ->name('graphs.impact');
});
