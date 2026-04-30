<?php

declare(strict_types=1);

use App\Modules\Integrations\Github\Http\Controllers\GithubAppController;
use App\Modules\Integrations\Github\Http\Controllers\GithubIntegrationSettingsController;
use Illuminate\Support\Facades\Route;

// Webhook — no auth, signature-verified inside the handler. CSRF is
// excluded for the path in bootstrap/app.php.
Route::post('gh/webhook', [GithubAppController::class, 'webhook'])
    ->name('github-app.webhook');

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('gh/install', [GithubAppController::class, 'install'])
        ->name('github-app.install');
    Route::get('gh/install/callback', [GithubAppController::class, 'installCallback'])
        ->name('github-app.install.callback');
    Route::post('gh/sync', [GithubAppController::class, 'sync'])
        ->name('github-app.sync');

    Route::get('settings/github', [GithubIntegrationSettingsController::class, 'show'])
        ->name('settings.github');
});
