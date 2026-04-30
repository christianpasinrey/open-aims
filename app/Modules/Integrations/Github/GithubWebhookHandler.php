<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github;

use App\Modules\Integrations\Github\Models\GithubInstallation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a GitHub webhook signature, then dispatches by event type.
 * Synchronous-by-default for now; if throughput becomes an issue we can
 * push the body onto a queue and process it in a job.
 */
final class GithubWebhookHandler
{
    public function __construct(
        private readonly GithubAppService $github,
        private readonly LinkPullRequestAction $linkPr,
    ) {}

    /**
     * @return array{handled:bool,event:string,reason?:string}
     */
    public function handle(Request $request): array
    {
        $event = (string) $request->header('X-GitHub-Event', '');
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $secret = (string) config('services.github_app.webhook_secret', '');
        $body = (string) $request->getContent();

        if (! $this->github->verifyWebhook($signature, $body, $secret)) {
            return ['handled' => false, 'event' => $event, 'reason' => 'invalid signature'];
        }

        /** @var array<string,mixed> $payload */
        $payload = json_decode($body, true) ?: [];

        return match ($event) {
            'installation' => $this->handleInstallation($payload),
            'installation_repositories' => $this->handleInstallationRepositories($payload),
            'pull_request' => $this->handlePullRequest($payload),
            'ping' => ['handled' => true, 'event' => 'ping'],
            default => ['handled' => true, 'event' => $event, 'reason' => 'ignored'],
        };
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{handled:bool,event:string,reason?:string}
     */
    private function handleInstallation(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        $installationId = (int) ($payload['installation']['id'] ?? 0);
        if ($installationId === 0) {
            return ['handled' => false, 'event' => 'installation', 'reason' => 'missing id'];
        }

        $installation = GithubInstallation::query()
            ->where('installation_id', (string) $installationId)
            ->first();

        if ($installation === null) {
            // We never persist an installation we don't know about — the
            // workspace mapping is established in the install callback.
            // Log so we can spot orphans during diagnostics.
            Log::info('github-app: webhook for unknown installation', [
                'installation_id' => $installationId,
                'action' => $action,
            ]);

            return ['handled' => true, 'event' => 'installation', 'reason' => 'unknown installation'];
        }

        match ($action) {
            'suspend' => $installation->forceFill(['suspended_at' => now()])->save(),
            'unsuspend' => $installation->forceFill(['suspended_at' => null])->save(),
            'deleted' => $installation->delete(),
            default => null,
        };

        return ['handled' => true, 'event' => 'installation'];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{handled:bool,event:string,reason?:string}
     */
    private function handleInstallationRepositories(array $payload): array
    {
        Log::info('github-app: installation_repositories', [
            'action' => $payload['action'] ?? null,
            'installation_id' => $payload['installation']['id'] ?? null,
            'added' => count($payload['repositories_added'] ?? []),
            'removed' => count($payload['repositories_removed'] ?? []),
        ]);

        return ['handled' => true, 'event' => 'installation_repositories'];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{handled:bool,event:string,reason?:string}
     */
    private function handlePullRequest(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        $allowed = ['opened', 'edited', 'reopened', 'closed', 'synchronize', 'ready_for_review'];
        if (! in_array($action, $allowed, true)) {
            return ['handled' => true, 'event' => 'pull_request', 'reason' => 'ignored action: '.$action];
        }

        $installationId = (int) ($payload['installation']['id'] ?? 0);
        if ($installationId === 0) {
            return ['handled' => false, 'event' => 'pull_request', 'reason' => 'missing installation'];
        }

        $installation = GithubInstallation::query()
            ->where('installation_id', (string) $installationId)
            ->first();
        if ($installation === null) {
            return ['handled' => true, 'event' => 'pull_request', 'reason' => 'unknown installation'];
        }

        /** @var array<string,mixed> $pull */
        $pull = is_array($payload['pull_request'] ?? null) ? $payload['pull_request'] : [];
        if ($pull === []) {
            return ['handled' => false, 'event' => 'pull_request', 'reason' => 'empty pull_request'];
        }

        ($this->linkPr)($installation, $pull);

        return ['handled' => true, 'event' => 'pull_request'];
    }
}
