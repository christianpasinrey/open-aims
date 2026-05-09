<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Http\Controllers;

use App\Modules\Integrations\Github\GithubAppService;
use App\Modules\Integrations\Github\GithubEventIngester;
use App\Modules\Integrations\Github\GithubWebhookHandler;
use App\Modules\Integrations\Github\LinkPullRequestAction;
use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        private readonly GithubEventIngester $ingester,
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
            return redirect('/workspace/github')->withErrors([
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
            return redirect('/workspace/github')->withErrors([
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

        return redirect('/workspace/github?status='.urlencode($setupAction === 'install' ? 'installed' : 'updated'));
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
            return redirect('/workspace/github')->withErrors([
                'github_app' => 'No active workspace.',
            ]);
        }

        $installations = GithubInstallation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('suspended_at')
            ->get();

        if ($installations->isEmpty()) {
            return redirect('/workspace/github')->withErrors([
                'github_app' => 'No active installations for this workspace.',
            ]);
        }

        $totals = [
            'repos_synced' => 0,
            'branches_synced' => 0,
            'pulls_synced' => 0,
            'issues_linked' => 0,
        ];
        foreach ($installations as $installation) {
            try {
                $result = $this->syncInstallation($installation);
                foreach ($totals as $key => $_) {
                    $totals[$key] += $result[$key] ?? 0;
                }
            } catch (\Throwable $e) {
                Log::warning('github-app: sync failed', [
                    'installation_id' => $installation->installation_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $query = http_build_query([
            'status' => 'synced',
            'linked' => $totals['issues_linked'],
            'branches' => $totals['branches_synced'],
            'pulls' => $totals['pulls_synced'],
            'repos' => $totals['repos_synced'],
        ]);

        return redirect('/workspace/github?'.$query);
    }

    /**
     * Recover installations that were created on github.com directly
     * (i.e. without going through our /gh/install state-passing flow) by
     * asking the App for the list of all its installations and attaching
     * any unknown ones to the current workspace.
     */
    public function reconcile(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        if ($workspace === null) {
            return redirect('/workspace/github')->withErrors([
                'github_app' => 'No active workspace.',
            ]);
        }

        $installations = $this->github->listInstallations();
        if ($installations === null) {
            return redirect('/workspace/github')->withErrors([
                'github_app' => 'GitHub App is not configured (missing app id or private key).',
            ]);
        }

        $attached = 0;
        $refreshed = 0;
        foreach ($installations as $remote) {
            $remoteId = (int) ($remote['id'] ?? 0);
            if ($remoteId === 0) {
                continue;
            }

            $login = (string) ($remote['account']['login'] ?? 'unknown');
            $type = (string) ($remote['account']['type'] ?? 'Organization');
            $repoSel = (string) ($remote['repository_selection'] ?? 'all');

            $existing = GithubInstallation::query()
                ->where('installation_id', (string) $remoteId)
                ->first();
            if ($existing !== null) {
                // Refresh stale metadata (e.g. installations adopted before
                // the App's private key was available, so they got stuck
                // with 'unknown' / defaults).
                $updates = [];
                if ($existing->account_login !== $login) {
                    $updates['account_login'] = $login;
                }
                if ($existing->account_type !== $type) {
                    $updates['account_type'] = $type;
                }
                if ($existing->repository_selection !== $repoSel) {
                    $updates['repository_selection'] = $repoSel;
                }
                if ($updates !== []) {
                    $existing->forceFill($updates)->save();
                    $refreshed++;
                }

                continue;
            }

            GithubInstallation::create([
                'workspace_id' => $workspace->id,
                'installation_id' => (string) $remoteId,
                'account_login' => $login,
                'account_type' => $type,
                'repository_selection' => $repoSel,
                'suspended_at' => null,
            ]);
            $attached++;
        }

        return redirect('/workspace/github?status=reconciled&attached='.$attached.'&refreshed='.$refreshed);
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

        $response = Http::withHeaders([
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
     * Full backfill: for every repo the installation can see, write a
     * `github_repos` row, then enumerate branches + PRs from the REST
     * API and persist them to `github_branches` /
     * `github_pull_requests` via the ingester helpers. Finally, run
     * `LinkPullRequestAction` so any matched issues get linked the
     * same way as on a webhook delivery.
     *
     * Also opportunistically refreshes the installation row's metadata
     * (account_login / type / repository_selection) — useful when the
     * row was created before the App's private key was available, so
     * the original metadata fetch failed and got persisted as 'unknown'.
     *
     * @return array{repos_synced:int, branches_synced:int, pulls_synced:int, issues_linked:int}
     */
    private function syncInstallation(GithubInstallation $installation): array
    {
        $this->refreshInstallationMeta($installation);

        $reposSynced = 0;
        $branchesSynced = 0;
        $pullsSynced = 0;
        $issuesLinked = 0;

        $repos = $this->github->listRepos((int) $installation->installation_id);

        foreach ($repos as $repoData) {
            $owner = (string) ($repoData['owner']['login'] ?? '');
            $name = (string) ($repoData['name'] ?? '');
            if ($owner === '' || $name === '') {
                continue;
            }

            $repoModel = $this->ingester->upsertRepoFromApi($installation, $repoData);
            if ($repoModel === null) {
                continue;
            }
            $reposSynced++;

            $branches = $this->github->listBranches((int) $installation->installation_id, $owner, $name);
            foreach ($branches as $branchData) {
                $this->ingester->upsertBranchFromApi($repoModel, $branchData);
                $branchesSynced++;
            }

            $pulls = $this->github->listOpenPulls((int) $installation->installation_id, $owner, $name);
            foreach ($pulls as $pull) {
                $this->ingester->upsertPullRequestFromApi($repoModel, $pull);
                $pullsSynced++;
                $issuesLinked += ($this->linkPr)($installation, $pull);
            }
        }

        return [
            'repos_synced' => $reposSynced,
            'branches_synced' => $branchesSynced,
            'pulls_synced' => $pullsSynced,
            'issues_linked' => $issuesLinked,
        ];
    }

    private function refreshInstallationMeta(GithubInstallation $installation): void
    {
        $meta = $this->fetchInstallationMeta((int) $installation->installation_id);
        $updates = [];
        if (is_string($meta['account_login']) && $meta['account_login'] !== '' && $meta['account_login'] !== $installation->account_login) {
            $updates['account_login'] = $meta['account_login'];
        }
        if (is_string($meta['account_type']) && $meta['account_type'] !== '' && $meta['account_type'] !== $installation->account_type) {
            $updates['account_type'] = $meta['account_type'];
        }
        if (is_string($meta['repository_selection']) && $meta['repository_selection'] !== '' && $meta['repository_selection'] !== $installation->repository_selection) {
            $updates['repository_selection'] = $meta['repository_selection'];
        }
        if ($updates !== []) {
            $installation->forceFill($updates)->save();
        }
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
