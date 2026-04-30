<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github;

use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Integrations\Github\Models\GithubLinkedPullRequest;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
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
        $linked = 0;

        foreach ($issues as $issue) {
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

            if ($row->wasRecentlyCreated || $row->wasChanged()) {
                $linked++;
            }
        }

        return $linked;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Issue>
     */
    private function matchIssues(GithubInstallation $installation, string $branch): \Illuminate\Support\Collection
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
