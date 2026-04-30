<?php

declare(strict_types=1);

use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
});

describe('GithubAppController::install', function () {
    it('redirects to the configured install url with the workspace slug as state', function () {
        config()->set('services.github_app.install_url', 'https://github.com/apps/example/installations/new');

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('github-app.install'));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('github.com/apps/example/installations/new');
        expect($response->headers->get('Location'))->toContain('state='.urlencode($this->workspace->slug));
    });

    it('falls back to https://github.com/apps when no install url and no app name are configured', function () {
        config()->set('services.github_app.install_url', '');
        config()->set('services.github_app.app_name', '');

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('github-app.install'));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('github.com/apps');
    });
});

describe('GithubAppController::webhook signature verification', function () {
    it('rejects webhook without signature header', function () {
        config()->set('services.github_app.webhook_secret', 'secret');

        $response = $this->postJson(route('github-app.webhook'), ['action' => 'opened']);

        $response->assertStatus(401);
        expect($response->json('reason'))->toBe('invalid signature');
    });

    it('rejects webhook with bad signature', function () {
        config()->set('services.github_app.webhook_secret', 'secret');

        $body = json_encode(['action' => 'opened']);
        $response = $this->call(
            'POST',
            route('github-app.webhook'),
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => 'sha256=deadbeef',
                'HTTP_X-GitHub-Event' => 'pull_request',
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );

        $response->assertStatus(401);
    });

    it('accepts a valid HMAC signature and dispatches by event type', function () {
        config()->set('services.github_app.webhook_secret', 'super-secret');

        $body = json_encode(['zen' => 'Speak like a human.']);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'super-secret');

        $response = $this->call(
            'POST',
            route('github-app.webhook'),
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'ping',
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );

        $response->assertOk();
        expect($response->json('ok'))->toBeTrue();
    });

    it('handles a pull_request event end-to-end and creates a linked PR', function () {
        config()->set('services.github_app.webhook_secret', 'secret');

        $install = GithubInstallation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'installation_id' => '99001',
        ]);

        $issue = makeIssue($this->team, $this->workspace, makeWorkspaceFixtureState($this->workspace), []);
        $issue->forceFill(['git_branch_name' => 'feature/welcome'])->save();

        $payload = [
            'action' => 'opened',
            'installation' => ['id' => 99001],
            'pull_request' => [
                'number' => 5,
                'node_id' => 'PR_123',
                'title' => 'Welcome PR',
                'state' => 'open',
                'html_url' => 'https://github.com/example/repo/pull/5',
                'head' => ['ref' => 'feature/welcome'],
                'user' => ['login' => 'octocat'],
                'created_at' => now()->toIso8601String(),
            ],
        ];

        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'secret');

        $response = $this->call(
            'POST',
            route('github-app.webhook'),
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'pull_request',
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );

        $response->assertOk();
        $this->assertDatabaseHas('github_linked_pull_requests', [
            'installation_id' => $install->id,
            'pr_node_id' => 'PR_123',
            'issue_id' => $issue->id,
        ]);
    });
});

/**
 * Helper used inline for the integration test above — the existing
 * makeIssue() requires a state, so we reuse the fixture's first state.
 */
function makeWorkspaceFixtureState($workspace)
{
    return WorkflowState::query()
        ->whereIn('team_id', Team::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('id'))
        ->orderBy('position')
        ->first();
}
