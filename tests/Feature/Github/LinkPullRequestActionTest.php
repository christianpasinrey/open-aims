<?php

declare(strict_types=1);

use App\Modules\Integrations\Github\LinkPullRequestAction;
use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Integrations\Github\Models\GithubLinkedPullRequest;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('LinkPullRequestAction', function () {
    it('matches by exact git_branch_name first', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);
        $issue->forceFill(['git_branch_name' => 'feat/exact'])->save();

        $install = GithubInstallation::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $action = new LinkPullRequestAction;
        $linked = $action($install, [
            'number' => 7,
            'node_id' => 'PR_a',
            'title' => 'x',
            'state' => 'open',
            'html_url' => 'https://example.com',
            'head' => ['ref' => 'feat/exact'],
            'user' => ['login' => 'oc'],
        ]);

        expect($linked)->toBe(1);
        expect(GithubLinkedPullRequest::where('issue_id', $issue->id)->count())->toBe(1);
    });

    it('falls back to LAM-N parsing when no exact branch match', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);

        $install = GithubInstallation::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $action = new LinkPullRequestAction;
        $linked = $action($install, [
            'number' => 3,
            'node_id' => 'PR_b',
            'title' => 'fallback',
            'state' => 'open',
            'html_url' => 'https://example.com',
            'head' => ['ref' => 'octocat/eng-'.$issue->number.'-fix'],
            'user' => ['login' => 'oc'],
        ]);

        expect($linked)->toBe(1);
        expect(GithubLinkedPullRequest::where('issue_id', $issue->id)->count())->toBe(1);
    });

    it('returns 0 when neither branch nor identifier matches', function () {
        $install = GithubInstallation::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $action = new LinkPullRequestAction;
        $linked = $action($install, [
            'number' => 9,
            'node_id' => 'PR_c',
            'title' => 'x',
            'state' => 'open',
            'html_url' => 'https://example.com',
            'head' => ['ref' => 'no-issue-id-here'],
            'user' => ['login' => 'oc'],
        ]);

        expect($linked)->toBe(0);
    });

    it('upserts by (installation_id, pr_node_id) — re-running does not duplicate', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);
        $issue->forceFill(['git_branch_name' => 'feat/dupe'])->save();

        $install = GithubInstallation::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $payload = [
            'number' => 1,
            'node_id' => 'PR_dupe',
            'title' => 'dup',
            'state' => 'open',
            'html_url' => 'https://example.com',
            'head' => ['ref' => 'feat/dupe'],
            'user' => ['login' => 'oc'],
        ];

        $action = new LinkPullRequestAction;
        $action($install, $payload);
        $action($install, $payload);

        expect(GithubLinkedPullRequest::where('installation_id', $install->id)
            ->where('pr_node_id', 'PR_dupe')
            ->count())->toBe(1);
    });

    it('resolves merged state from merged_at', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);
        $issue->forceFill(['git_branch_name' => 'feat/m'])->save();

        $install = GithubInstallation::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $action = new LinkPullRequestAction;
        $action($install, [
            'number' => 1,
            'node_id' => 'PR_merged',
            'title' => 'm',
            'state' => 'closed',
            'merged_at' => now()->toIso8601String(),
            'html_url' => 'https://example.com',
            'head' => ['ref' => 'feat/m'],
            'user' => ['login' => 'oc'],
        ]);

        $row = GithubLinkedPullRequest::where('pr_node_id', 'PR_merged')->first();
        expect($row->pr_state)->toBe('merged');
    });
});
