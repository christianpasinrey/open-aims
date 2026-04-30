<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Http\Controllers;

use App\Modules\Integrations\Github\GithubAppService;
use App\Modules\Integrations\Github\GithubWebhookHandler;
use App\Modules\Integrations\Github\LinkPullRequestAction;
use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Handles the install round-trip with GitHub plus the webhook endpoint.
 *
 *   GET  /gh/install                     — redirect to github.com/apps/.../installations/new
 *   GET  /gh/install/callback            — record installation, kick off sync
 *   POST /gh/webhook                     — receive events
 */
final class GithubAppController
{
    public function __construct(
        private readonly GithubAppService $github,
        private readonly GithubWebhookHandler $webhook,
        private readonly LinkPullRequestAction $linkPr,
    ) {}

    public function install(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $state = $workspace?->slug ?? '';

        return redirect()->away($this->github->installUrl($state));
    }

    public function installCallback(Request $request): RedirectResponse
    {
        $installationId = (string) $request->query('installation_id', '');
        $setupAction = (string) $request->query('setup_action', '');
        $state = (string) $request->query('state', '');

        if ($installationId === '') {
            return redirect('/settings/github')->withErrors([
                'github_app' => 'GitHub did not return an installation_id.',
            ]);
        }

        $workspace = null;
        if ($state !== '') {
            $workspace = Workspace::query()->where('slug', $state)->first();
        }
        if ($workspace === null) {
            $workspace = $this->currentWorkspace();
        }
        if ($workspace === null) {
            return redirect('/settings/github')->withErrors([
                'github_app' => 'No active workspace to attach the installation to.',
            ]);
        }

        // Load the installation metadata via the App JWT so we know the
        // org login + repo selection, even before any webhook fires.
        $meta = $this->fetchInstallationMeta((int) $installationId);

        $installation = GithubInstallation::query()->updateOrCreate(
            ['installation_id' => $installationId],
            [
                'workspace_id' => $workspace->id,
                'account_login' => $meta['account_login'] ?? 'unknown',
                'account_type' => $meta['account_type'] ?? 'Organization',
                'repository_selection' => $meta['repository_selection'] ?? 'all',
                'suspended_at' => null,
            ],
        );

        // Trigger an initial sync of open PRs synchronously when possible
        // — at this point queues might not be configured for the user.
        try {
            $this->syncInstallation($installation);
        } catch (\Throwable $e) {
            Log::warning('github-app: install callback sync failed', [
                'installation_id' => $installation->installation_id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect('/settings/github?status='.urlencode($setupAction === 'install' ? 'installed' : 'updated'));
    }

    public function webhook(Request $request): JsonResponse
    {
        $result = $this->webhook->handle($request);

        if (($result['handled'] ?? false) === false) {
            $reason = (string) ($result['reason'] ?? 'rejected');
            $status = $reason === 'invalid signature' ? 401 : 422;

            return new JsonResponse(['ok' => false, 'reason' => $reason], $status);
        }

        return new JsonResponse(['ok' => true]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        if ($workspace === null) {
            return redirect('/settings/github')->withErrors([
                'github_app' => 'No active workspace.',
            ]);
        }

        $installations = GithubInstallation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('suspended_at')
            ->get();

        if ($installations->isEmpty()) {
            return redirect('/settings/github')->withErrors([
                'github_app' => 'No active installations for this workspace.',
            ]);
        }

        $linked = 0;
        foreach ($installations as $installation) {
            try {
                $linked += $this->syncInstallation($installation);
            } catch (\Throwable $e) {
                Log::warning('github-app: sync failed', [
                    'installation_id' => $installation->installation_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect('/settings/github?status=synced&linked='.$linked);
    }

    /**
     * Issue REST calls against the App JWT (no installation token yet)
     * to fetch installation metadata. Returns null fields when the App
     * is not configured so the UI shows "unknown" but doesn't crash.
     *
     * @return array{account_login:?string,account_type:?string,repository_selection:?string}
     */
    private function fetchInstallationMeta(int $installationId): array
    {
        $jwt = $this->github->appJwt();
        if ($jwt === null) {
            return [
                'account_login' => null,
                'account_type' => null,
                'repository_selection' => null,
            ];
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'Authorization' => 'Bearer '.$jwt,
            'User-Agent' => 'aims-github-app',
        ])->get("https://api.github.com/app/installations/{$installationId}");

        if (! $response->successful()) {
            return [
                'account_login' => null,
                'account_type' => null,
                'repository_selection' => null,
            ];
        }

        return [
            'account_login' => (string) $response->json('account.login', 'unknown'),
            'account_type' => (string) $response->json('account.type', 'Organization'),
            'repository_selection' => (string) $response->json('repository_selection', 'all'),
        ];
    }

    /**
     * Pull every open PR in every repo the installation can see, then
     * run the link action against each. Returns the number of links
     * inserted/updated.
     */
    private function syncInstallation(GithubInstallation $installation): int
    {
        $repos = $this->github->listRepos((int) $installation->installation_id);
        $linked = 0;

        foreach ($repos as $repo) {
            $owner = (string) ($repo['owner']['login'] ?? '');
            $name = (string) ($repo['name'] ?? '');
            if ($owner === '' || $name === '') {
                continue;
            }
            $pulls = $this->github->listOpenPulls((int) $installation->installation_id, $owner, $name);
            foreach ($pulls as $pull) {
                $linked += ($this->linkPr)($installation, $pull);
            }
        }

        return $linked;
    }

    private function currentWorkspace(): ?Workspace
    {
        if (! app()->bound('current.workspace')) {
            return null;
        }
        $workspace = app('current.workspace');

        return $workspace instanceof Workspace ? $workspace : null;
    }
}
