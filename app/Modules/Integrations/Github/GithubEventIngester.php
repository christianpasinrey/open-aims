<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github;

use App\Modules\Integrations\Github\Models\GithubBranch;
use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Integrations\Github\Models\GithubPullRequest;
use App\Modules\Integrations\Github\Models\GithubRepo;
use App\Modules\Integrations\Github\Models\GithubWebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Two-step pipeline for incoming GitHub webhooks:
 *
 *   1. record(): persist the raw delivery in github_webhook_events.
 *      Idempotent on delivery_id — re-deliveries are no-ops.
 *
 *   2. process(): derive github_repos / github_branches /
 *      github_pull_requests rows from the payload, plus the existing
 *      issue auto-link via LinkPullRequestAction. Also runs on demand
 *      against historical events (replay).
 *
 * Steps are decoupled so we never lose data: even if derivation throws,
 * the raw row stays. The webhook controller can retry, or a queue worker
 * can sweep `processed_at IS NULL` rows on its own pace.
 */
final class GithubEventIngester
{
    public function __construct(
        private readonly LinkPullRequestAction $linkPr,
    ) {}

    /**
     * Persist the raw delivery and process it. Returns the row.
     * Idempotent: a re-delivered event returns the existing row without
     * re-processing.
     *
     * @param  array<string,mixed>  $payload
     */
    public function ingest(
        string $deliveryId,
        string $eventType,
        array $payload,
        bool $signatureOk,
    ): GithubWebhookEvent {
        $existing = GithubWebhookEvent::query()
            ->where('delivery_id', $deliveryId)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $installationExternalId = isset($payload['installation']['id'])
            ? (string) $payload['installation']['id']
            : null;
        $installation = $installationExternalId !== null
            ? GithubInstallation::query()
                ->where('installation_id', $installationExternalId)
                ->first()
            : null;

        $event = GithubWebhookEvent::create([
            'delivery_id' => $deliveryId,
            'installation_id' => $installation?->id,
            'installation_external_id' => $installationExternalId,
            'event_type' => $eventType,
            'action' => isset($payload['action']) && is_string($payload['action'])
                ? mb_substr($payload['action'], 0, 64)
                : null,
            'repository_full_name' => isset($payload['repository']['full_name'])
                ? (string) $payload['repository']['full_name']
                : null,
            'sender_login' => isset($payload['sender']['login'])
                ? (string) $payload['sender']['login']
                : null,
            'signature_ok' => $signatureOk,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($signatureOk) {
            $this->process($event);
        }

        return $event;
    }

    /**
     * Run derivation for a stored event. Marks processed_at on success,
     * stamps processing_error otherwise. Safe to re-run.
     */
    public function process(GithubWebhookEvent $event): void
    {
        try {
            $payload = is_array($event->payload) ? $event->payload : [];
            $installation = $event->installation
                ?: ($event->installation_external_id !== null
                    ? GithubInstallation::query()
                        ->where('installation_id', $event->installation_external_id)
                        ->first()
                    : null);

            $repo = $this->upsertRepo($installation, $payload);

            switch ($event->event_type) {
                case 'push':
                    $this->handlePush($repo, $payload);
                    break;
                case 'pull_request':
                    $pr = $this->upsertPullRequest($repo, $payload);
                    if ($installation !== null && $pr !== null && isset($payload['pull_request']) && is_array($payload['pull_request'])) {
                        ($this->linkPr)($installation, $payload['pull_request']);
                    }
                    break;
                case 'create':
                case 'delete':
                    $this->handleRefLifecycle($repo, $event->event_type, $payload);
                    break;
                default:
                    // installation, installation_repositories, ping, etc. —
                    // nothing to derive here yet; the raw row is enough.
                    break;
            }

            $event->forceFill([
                'processed_at' => now(),
                'processing_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('github-app: event derivation failed', [
                'delivery_id' => $event->delivery_id,
                'event_type' => $event->event_type,
                'error' => $e->getMessage(),
            ]);
            $event->forceFill([
                'processing_error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function upsertRepo(?GithubInstallation $installation, array $payload): ?GithubRepo
    {
        $repoData = is_array($payload['repository'] ?? null) ? $payload['repository'] : null;
        if ($repoData === null || $installation === null) {
            return null;
        }
        $githubId = (int) ($repoData['id'] ?? 0);
        if ($githubId === 0) {
            return null;
        }

        return GithubRepo::query()->updateOrCreate(
            ['github_id' => $githubId],
            [
                'installation_id' => $installation->id,
                'node_id' => isset($repoData['node_id']) ? (string) $repoData['node_id'] : null,
                'full_name' => (string) ($repoData['full_name'] ?? ''),
                'default_branch' => (string) ($repoData['default_branch'] ?? 'main'),
                'language' => isset($repoData['language']) ? (string) $repoData['language'] : null,
                'private' => (bool) ($repoData['private'] ?? true),
                'archived' => (bool) ($repoData['archived'] ?? false),
                'html_url' => isset($repoData['html_url']) ? (string) $repoData['html_url'] : null,
                'description' => isset($repoData['description']) ? (string) $repoData['description'] : null,
                'last_pushed_at' => $this->parseTimestamp($repoData['pushed_at'] ?? null),
            ],
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function handlePush(?GithubRepo $repo, array $payload): void
    {
        if ($repo === null) {
            return;
        }
        $ref = (string) ($payload['ref'] ?? '');
        if (! str_starts_with($ref, 'refs/heads/')) {
            return; // tag or other ref — ignore
        }
        $branchName = mb_substr($ref, mb_strlen('refs/heads/'));
        $deleted = (bool) ($payload['deleted'] ?? false);
        $headSha = (string) ($payload['after'] ?? '');
        $pusher = isset($payload['pusher']['name'])
            ? (string) $payload['pusher']['name']
            : (isset($payload['sender']['login']) ? (string) $payload['sender']['login'] : null);
        $headTimestamp = $this->parseTimestamp(
            $payload['head_commit']['timestamp'] ?? ($payload['repository']['pushed_at'] ?? null)
        );

        $branch = GithubBranch::withTrashed()
            ->where('repo_id', $repo->id)
            ->where('name', $branchName)
            ->first();

        if ($branch === null) {
            $branch = GithubBranch::create([
                'repo_id' => $repo->id,
                'name' => $branchName,
                'head_sha' => $headSha !== '' ? $headSha : null,
                'last_pusher_login' => $pusher,
                'last_pushed_at' => $headTimestamp,
            ]);
        } else {
            $updates = [];
            if ($headSha !== '' && $branch->head_sha !== $headSha) {
                $updates['head_sha'] = $headSha;
            }
            if ($pusher !== null) {
                $updates['last_pusher_login'] = $pusher;
            }
            if ($headTimestamp !== null) {
                $updates['last_pushed_at'] = $headTimestamp;
            }
            if ($branch->trashed()) {
                $branch->restore();
            }
            if ($updates !== []) {
                $branch->forceFill($updates)->save();
            }
        }

        if ($deleted) {
            $branch->delete();
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function upsertPullRequest(?GithubRepo $repo, array $payload): ?GithubPullRequest
    {
        if ($repo === null) {
            return null;
        }
        $pr = is_array($payload['pull_request'] ?? null) ? $payload['pull_request'] : null;
        if ($pr === null) {
            return null;
        }
        $githubId = (int) ($pr['id'] ?? 0);
        if ($githubId === 0) {
            return null;
        }

        $headBranchName = isset($pr['head']['ref']) ? (string) $pr['head']['ref'] : null;
        $headBranchId = null;
        if ($headBranchName !== null) {
            $branch = GithubBranch::withTrashed()
                ->where('repo_id', $repo->id)
                ->where('name', $headBranchName)
                ->first();
            if ($branch === null) {
                $branch = GithubBranch::create([
                    'repo_id' => $repo->id,
                    'name' => $headBranchName,
                    'head_sha' => isset($pr['head']['sha']) ? (string) $pr['head']['sha'] : null,
                ]);
            }
            $headBranchId = $branch->id;
        }

        $merged = (bool) ($pr['merged'] ?? false) || ($pr['merged_at'] ?? null) !== null;
        $state = (string) ($pr['state'] ?? 'open');

        return GithubPullRequest::query()->updateOrCreate(
            ['github_id' => $githubId],
            [
                'repo_id' => $repo->id,
                'node_id' => isset($pr['node_id']) ? (string) $pr['node_id'] : null,
                'number' => (int) ($pr['number'] ?? 0),
                'title' => mb_substr((string) ($pr['title'] ?? ''), 0, 500),
                'body' => isset($pr['body']) ? (string) $pr['body'] : null,
                'state' => $state,
                'merged' => $merged,
                'draft' => (bool) ($pr['draft'] ?? false),
                'author_login' => isset($pr['user']['login']) ? (string) $pr['user']['login'] : null,
                'author_id' => isset($pr['user']['id']) ? (int) $pr['user']['id'] : null,
                'head_branch_name' => $headBranchName,
                'head_branch_id' => $headBranchId,
                'head_sha' => isset($pr['head']['sha']) ? (string) $pr['head']['sha'] : null,
                'base_ref' => isset($pr['base']['ref']) ? (string) $pr['base']['ref'] : null,
                'merge_commit_sha' => isset($pr['merge_commit_sha']) && $pr['merge_commit_sha'] !== null
                    ? (string) $pr['merge_commit_sha'] : null,
                'additions' => (int) ($pr['additions'] ?? 0),
                'deletions' => (int) ($pr['deletions'] ?? 0),
                'changed_files' => (int) ($pr['changed_files'] ?? 0),
                'commits_count' => (int) ($pr['commits'] ?? 0),
                'html_url' => isset($pr['html_url']) ? (string) $pr['html_url'] : null,
                'opened_at' => $this->parseTimestamp($pr['created_at'] ?? null),
                'closed_at' => $this->parseTimestamp($pr['closed_at'] ?? null),
                'merged_at' => $this->parseTimestamp($pr['merged_at'] ?? null),
            ],
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function handleRefLifecycle(?GithubRepo $repo, string $event, array $payload): void
    {
        if ($repo === null) {
            return;
        }
        $refType = (string) ($payload['ref_type'] ?? '');
        if ($refType !== 'branch') {
            return;
        }
        $name = (string) ($payload['ref'] ?? '');
        if ($name === '') {
            return;
        }

        $branch = GithubBranch::withTrashed()
            ->where('repo_id', $repo->id)
            ->where('name', $name)
            ->first();

        if ($event === 'create') {
            if ($branch === null) {
                GithubBranch::create([
                    'repo_id' => $repo->id,
                    'name' => $name,
                    'head_sha' => isset($payload['after']) ? (string) $payload['after'] : null,
                ]);
            } elseif ($branch->trashed()) {
                $branch->restore();
            }
        } elseif ($event === 'delete' && $branch !== null && ! $branch->trashed()) {
            $branch->delete();
        }
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_int($value)) {
                return Carbon::createFromTimestamp($value);
            }
            if (is_string($value)) {
                return Carbon::parse($value);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
