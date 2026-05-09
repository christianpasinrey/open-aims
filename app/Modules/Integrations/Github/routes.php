<?php

declare(strict_types=1);

use App\Modules\Integrations\Github\Http\Controllers\GithubAppController;
use App\Modules\Integrations\Github\Http\Controllers\GithubIntegrationSettingsController;
use App\Modules\Integrations\Github\Http\Controllers\GithubLinkController;
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
    Route::post('gh/reconcile', [GithubAppController::class, 'reconcile'])
        ->name('github-app.reconcile');

    // GitHub integration belongs under workspace settings, not the
    // user-profile settings (it's a workspace-scoped resource).
    Route::get('workspace/github', [GithubIntegrationSettingsController::class, 'show'])
        ->name('workspace.github');

    // Back-compat: old links still redirect to the new home.
    Route::redirect('settings/github', '/workspace/github')
        ->name('settings.github');

    // Polymorphic GitHub links — manual create / remove from the issue
    // and project right-rail picker. Auto-links written by
    // LinkPullRequestAction reuse the same table but skip these endpoints.
    Route::post('github-links', [GithubLinkController::class, 'store'])
        ->name('github-links.store');
    Route::delete('github-links/{id}', [GithubLinkController::class, 'destroy'])
        ->whereNumber('id')
        ->name('github-links.destroy');
});
