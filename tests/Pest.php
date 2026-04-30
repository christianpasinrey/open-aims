<?php

use App\Models\User;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Vite stubbing and factory namespace resolution are configured in
| Tests\TestCase::setUp() so both Pest and PHPUnit class-style tests share
| the same setup.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions / Helpers
|--------------------------------------------------------------------------
*/

function something()
{
    // ..
}

/**
 * Build a complete workspace fixture: workspace, owner user (membership),
 * one team with the seven canonical workflow states, and bind it as the
 * current workspace in the container.
 *
 * @return array{workspace:Workspace,user:User,team:Team,states:array<string,WorkflowState>}
 */
function makeWorkspaceFixture(array $overrides = []): array
{
    $user = User::factory()->create($overrides['user'] ?? []);

    $workspace = Workspace::factory()->create(array_merge(
        ['owner_user_id' => $user->id],
        $overrides['workspace'] ?? [],
    ));

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    app()->instance('current.workspace', $workspace);

    $team = Team::factory()->create(array_merge(
        ['workspace_id' => $workspace->id, 'key' => 'ENG', 'name' => 'Engineering'],
        $overrides['team'] ?? [],
    ));

    $stateDefs = [
        ['name' => 'Triage', 'type' => 'triage', 'color' => '#94a3b8', 'position' => 0],
        ['name' => 'Backlog', 'type' => 'backlog', 'color' => '#64748b', 'position' => 1],
        ['name' => 'Todo', 'type' => 'unstarted', 'color' => '#475569', 'position' => 2],
        ['name' => 'In Progress', 'type' => 'started', 'color' => '#facc15', 'position' => 3],
        ['name' => 'Done', 'type' => 'completed', 'color' => '#10b981', 'position' => 4],
        ['name' => 'Canceled', 'type' => 'canceled', 'color' => '#ef4444', 'position' => 5],
    ];

    $states = [];
    foreach ($stateDefs as $def) {
        $states[$def['name']] = WorkflowState::create([
            'team_id' => $team->id,
            'name' => $def['name'],
            'type' => $def['type'],
            'color' => $def['color'],
            'position' => $def['position'],
        ]);
    }

    return compact('workspace', 'user', 'team', 'states');
}

/**
 * Quick helper to build an issue under the given team using the next number.
 */
function makeIssue(Team $team, Workspace $workspace, WorkflowState $state, array $overrides = []): Issue
{
    $team->refresh();
    $next = ((int) $team->issue_counter) + 1;
    $team->update(['issue_counter' => $next]);

    return Issue::create(array_merge([
        'workspace_id' => $workspace->id,
        'team_id' => $team->id,
        'number' => $next,
        'title' => 'Test issue',
        'workflow_state_id' => $state->id,
        'priority' => 0,
        'creator_user_id' => $workspace->owner_user_id,
    ], $overrides));
}
