<?php

use App\Http\Controllers\Auth\GithubOAuthController;
use App\Http\Controllers\PlanRawController;
use App\Modules\Issues\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('issues.index');
    }

    return inertia('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/issues')->name('dashboard');
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/plans/{plan}/raw', PlanRawController::class)->name('plans.raw');
});

// GitHub OAuth — sign in and account linking.
Route::get('gh/redirect', [GithubOAuthController::class, 'redirect'])->name('oauth.github.redirect');
Route::get('gh/callback', [GithubOAuthController::class, 'callback'])->name('oauth.github.callback');
Route::middleware(['auth'])->delete('gh/disconnect', [GithubOAuthController::class, 'disconnect'])
    ->name('oauth.github.disconnect');

require __DIR__.'/settings.php';
