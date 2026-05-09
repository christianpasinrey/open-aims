<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Http\Controllers;

use App\Modules\Integrations\Github\GithubAppService;
use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Integrations\Github\Models\GithubLinkedPullRequest;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the /settings/github sub-page. Read-only for now: the install
 * flow and "sync now" button are wired through GithubAppController.
 */
final class GithubIntegrationSettingsController
{
    public function __construct(private readonly GithubAppService $github) {}

    public function show(): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $installations = GithubInstallation::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('id')
            ->get();

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('key')
            ->get(['id', 'key', 'name', 'color']);

        $teamMap = $this->github->teamRepoMap();

        $teamsPayload = $teams->map(static fn (Team $t): array => [
            'id' => $t->id,
            'key' => $t->key,
            'name' => $t->name,
            'color' => $t->color,
            'repo_full_name' => $teamMap[strtoupper($t->key)] ?? null,
        ])->all();

        $recent = GithubLinkedPullRequest::query()
            ->whereIn('installation_id', $installations->pluck('id'))
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $issueIds = $recent->pluck('issue_id')->unique()->all();
        $issues = Issue::query()
            ->whereIn('id', $issueIds)
            ->with('team:id,key')
            ->get(['id', 'team_id', 'number', 'title']);

        $issueMap = $issues->mapWithKeys(static fn (Issue $i): array => [
            $i->id => [
                'identifier' => ($i->team?->key ?? 'ISS').'-'.$i->number,
                'title' => $i->title,
            ],
        ])->all();

        $recentPayload = $recent->map(static fn (GithubLinkedPullRequest $pr) => [
            'id' => $pr->id,
            'number' => $pr->pr_number,
            'title' => $pr->pr_title,
            'state' => $pr->pr_state,
            'url' => $pr->pr_url,
            'branch_name' => $pr->branch_name,
            'author_login' => $pr->author_login,
            'opened_at' => $pr->opened_at?->toIso8601String(),
            'issue' => $issueMap[$pr->issue_id] ?? null,
        ])->all();

        return Inertia::render('workspace/Github', [
            'configured' => $this->github->isConfigured(),
            'installUrl' => $this->github->installUrl($workspace->slug),
            'appName' => $this->github->appName(),
            'installations' => $installations->map(static fn (GithubInstallation $i): array => [
                'id' => $i->id,
                'installation_id' => $i->installation_id,
                'account_login' => $i->account_login,
                'account_type' => $i->account_type,
                'repository_selection' => $i->repository_selection,
                'suspended_at' => $i->suspended_at?->toIso8601String(),
                'created_at' => $i->created_at?->toIso8601String(),
            ])->all(),
            'teams' => $teamsPayload,
            'recentPullRequests' => $recentPayload,
        ]);
    }
}
