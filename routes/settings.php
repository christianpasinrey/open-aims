<?php

use App\Http\Controllers\Settings\DeveloperController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/developer', [DeveloperController::class, 'show'])
        ->name('settings.developer');
    Route::post('settings/developer/clients/dedupe', [DeveloperController::class, 'keepLatestPerClient'])
        ->name('settings.developer.dedupe');
    Route::post('settings/developer/clients/{clientId}/keep-latest', [DeveloperController::class, 'keepLatestForClient'])
        ->name('settings.developer.client.keep-latest');
    Route::delete('settings/developer/clients/{clientId}', [DeveloperController::class, 'revokeClient'])
        ->name('settings.developer.client.revoke');
});
