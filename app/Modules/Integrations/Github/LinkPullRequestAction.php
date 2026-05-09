<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github;

use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Integrations\Github\Models\GithubLink;
use App\Modules\Integrations\Github\Models\GithubLinkedPullRequest;
use App\Modules\Integrations\Github\Models\GithubPullRequest;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Match a single PR (as returned by the GitHub REST API or webhook
 * payload) to one or more aims issues and upsert a row in
 * `github_linked_pull_requests` per match.
 *
 * Matching strategy:
 *   1. Exact `issues.git_branch_name` match against the PR head branch.
 *   2. Fallback: parse `[A-Z]+-\d+` from the branch (repo's default
 *      branch naming includes the issue identifier near the start).
 *
 * Returns the number of issues newly linked or updated.
 */
final class LinkPullRequestAction
{
    /**
     * @param  array<string,mixed>  $pull  raw PR payload
     */
    public function __invoke(GithubInstallation $installation, array $pull): int
    {
        $branch = (string) ($pull['head']['ref'] ?? '');
        if ($branch === '') {
            return 0;
        }

        $issues = $this->matchIssues($installation, $branch);
        if ($issues->isEmpty()) {
            return 0;
        }

        $state = $this->resolveState($pull);
        $baseRef = (string) ($pull['base']['ref'] ?? 'main');
        $linked = 0;

        foreach ($issues as $issue) {
            $existing = GithubLinkedPullRequest::query()
                ->where('installation_id', $installation->id)
                ->where('pr_node_id', (string) ($pull['node_id'] ?? ''))
                ->first();
            $previousState = $existing?->pr_state;

            $row = GithubLinkedPullRequest::updateOrCreate(
                [
                    'installation_id' => $installation->id,
                    'pr_node_id' => (string) ($pull['node_id'] ?? ''),
                ],
                [
                    'issue_id' => $issue->id,
                    'pr_number' => (int) ($pull['number'] ?? 0),
                    'pr_title' => Str::limit((string) ($pull['title'] ?? ''), 250, ''),
                    'pr_state' => $state,
                    'pr_url' => (string) ($pull['html_url'] ?? ''),
                    'branch_name' => $branch,
                    'author_login' => $pull['user']['login'] ?? null,
                    'opened_at' => $pull['created_at'] ?? null,
                    'closed_at' => $pull['closed_at'] ?? null,
                    'merged_at' => $pull['merged_at'] ?? null,
                ],
            );

            if ($row->wasRecentlyCreated) {
                IssueActivity::create([
                    'issue_id' => $issue->id,
                    'actor_user_id' => null,
                    'kind' => 'branch_linked',
                    'payload' => [
                        'branch_name' => $branch,
                        'pr_number' => (int) ($pull['number'] ?? 0),
                        'pr_url' => (string) ($pull['html_url'] ?? ''),
                        'pr_title' => (string) ($pull['title'] ?? ''),
                    ],
                    'occurred_at' => now(),
                ]);
            }

            // Mirror the link into the polymorphic `github_links` table so
            // the picker UI sees auto-matched rows alongside manual ones.
            // The new ingester writes a GithubPullRequest row before this
            // action runs, so we look it up by node_id; if it's missing for
            // any defensive reason, we just skip the mirror write — the
            // legacy row above is still authoritative.
            $nodeId = (string) ($pull['node_id'] ?? '');
            if ($nodeId !== '') {
                $prRow = GithubPullRequest::query()
                    ->where('node_id', $nodeId)
                    ->first();
                if ($prRow !== null) {
                    GithubLink::query()->firstOrCreate(
                        [
                            'source_type' => 'pull_request',
                            'source_id' => $prRow->id,
                            'linkable_type' => Issue::class,
                            'linkable_id' => $issue->id,
                        ],
                        [
                            'auto' => true,
                            'linked_by_user_id' => null,
                        ],
                    );
                }
            }

            // Detect first transition into 'merged' and react: write a
            // branch_merged activity row + auto-transition the issue's
            // workflow state to the team's first 'completed' state.
            if ($state === 'merged' && $previousState !== 'merged') {
                IssueActivity::create([
                    'issue_id' => $issue->id,
                    'actor_user_id' => null,
                    'kind' => 'branch_merged',
                    'payload' => [
                        'branch_name' => $branch,
                        'base_branch' => $baseRef,
                        'pr_number' => (int) ($pull['number'] ?? 0),
                        'pr_url' => (string) ($pull['html_url'] ?? ''),
                    ],
                    'occurred_at' => now(),
                ]);

                if ($baseRef === 'main' || $baseRef === 'master') {
                    $this->autoTransitionToCompleted($issue);
                }
            }

            if ($row->wasRecentlyCreated || $row->wasChanged()) {
                $linked++;
            }
        }

        return $linked;
    }

    /**
     * Move the issue to the team's first 'completed' workflow state, but
     * only if it's not already in a terminal state (completed/canceled).
     * Emits a status_changed activity row matching what
     * IssueWriteController emits on manual changes.
     */
    private function autoTransitionToCompleted(Issue $issue): void
    {
        $current = WorkflowState::query()->find($issue->workflow_state_id);
        if ($current && in_array($current->type, ['completed', 'canceled'], true)) {
            return;
        }

        $completed = WorkflowState::query()
            ->where('team_id', $issue->team_id)
            ->where('type', 'completed')
            ->orderBy('position')
            ->first();
        if ($completed === null) {
            return;
        }

        $issue->forceFill([
            'workflow_state_id' => $completed->id,
            'completed_at' => $issue->completed_at ?? now(),
        ])->save();

        IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => null,
            'kind' => 'status_changed',
            'payload' => [
                'from' => $current ? [
                    'id' => $current->id,
                    'name' => $current->name,
                    'type' => $current->type,
                    'color' => $current->color,
                ] : null,
                'to' => [
                    'id' => $completed->id,
                    'name' => $completed->name,
                    'type' => $completed->type,
                    'color' => $completed->color,
                ],
                'auto' => true,
                'reason' => 'pr_merged',
            ],
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, Issue>
     */
    private function matchIssues(GithubInstallation $installation, string $branch): Collection
    {
        $workspaceId = $installation->workspace_id;

        // 1. Exact branch-name match — most reliable, set by repo's
        // "copy git branch name" feature.
        $exact = Issue::query()
            ->where('workspace_id', $workspaceId)
            ->where('git_branch_name', $branch)
            ->get();
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        // 2. Fallback: find an [A-Z]+-(\d+) token anywhere in the branch.
        if (preg_match('/([A-Za-z]+)-(\d+)/', $branch, $m) === 1) {
            $teamKey = strtoupper($m[1]);
            $number = (int) $m[2];

            $team = Team::query()
                ->where('workspace_id', $workspaceId)
                ->where('key', $teamKey)
                ->first();
            if ($team !== null) {
                $issue = Issue::query()
                    ->where('team_id', $team->id)
                    ->where('number', $number)
                    ->first();
                if ($issue !== null) {
                    return collect([$issue]);
                }
            }
        }

        return collect();
    }

    /**
     * @param  array<string,mixed>  $pull
     */
    private function resolveState(array $pull): string
    {
        if (! empty($pull['merged_at']) || ($pull['merged'] ?? false) === true) {
            return 'merged';
        }
        $state = (string) ($pull['state'] ?? 'open');

        return $state === 'closed' ? 'closed' : 'open';
    }

    /**
     * Convenience: best-effort cleanup of orphan rows that no longer
     * point at any matched issue. Currently unused — included so we have
     * a clear extension point if we add a "rebuild links" admin action.
     */
    public function pruneOrphans(GithubInstallation $installation): int
    {
        return DB::table('github_linked_pull_requests')
            ->where('installation_id', $installation->id)
            ->whereNotIn('issue_id', Issue::query()->select('id'))
            ->delete();
    }
}
