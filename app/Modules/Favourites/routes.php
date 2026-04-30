<?php

declare(strict_types=1);

use App\Modules\Favourites\Http\Controllers\FavouriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::post('favourites/toggle', [FavouriteController::class, 'toggle'])
        ->name('favourites.toggle');
    Route::patch('favourites/{id}', [FavouriteController::class, 'update'])
        ->whereNumber('id')
        ->name('favourites.update');
    Route::delete('favourites/{id}', [FavouriteController::class, 'destroy'])
        ->whereNumber('id')
        ->name('favourites.destroy');
});
