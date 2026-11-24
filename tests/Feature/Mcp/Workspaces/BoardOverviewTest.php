<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Mcp\Tools\BoardOverview;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\WorkspacesModuleManifest;
use Illuminate\Support\Facades\DB;

if (! function_exists('mcpToolJson')) {
    /**
     * Decode the JSON body a tool returned via Response::json().
     *
     * @return array<string, mixed>
     */
    function mcpToolJson(object $response): array
    {
        $method = new ReflectionMethod($response, 'content');
        $method->setAccessible(true);

        return json_decode(implode('', $method->invoke($response)), true) ?: [];
    }
}

/** Call board-overview as the fixture user and return the decoded body. */
function boardOverview(array $fix, array $arguments = []): array
{
    return mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(BoardOverview::class, $arguments)->assertOk()
    );
}

it('returns the workspace, the caller, teams with ordered states, projects and the active cycle', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id, 'name' => 'Apollo']);
    $project->teams()->attach($fix['team']->id);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Done'], ['project_id' => $project->id]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['project_id' => $project->id]);
    Cycle::factory()->create([
        'team_id' => $fix['team']->id, 'number' => 4, 'name' => 'Sprint 4',
        'starts_at' => now()->subDays(3), 'ends_at' => now()->addDays(4), 'completed_at' => null,
    ]);

    $body = boardOverview($fix);

    expect(array_keys($body))->toBe(['workspace', 'me', 'workspaces', 'teams', 'projects'])
        ->and($body['workspace'])->toBe([
            'id' => $fix['workspace']->id, 'name' => $fix['workspace']->name, 'slug' => $fix['workspace']->slug,
        ])
        ->and($body['me'])->toBe([
            'id' => $fix['user']->id, 'name' => $fix['user']->name, 'email' => $fix['user']->email,
        ])
        ->and($body['teams'])->toHaveCount(1)
        ->and(array_keys($body['teams'][0]))->toBe(['id', 'key', 'name', 'color', 'icon', 'states', 'active_cycle'])
        ->and($body['teams'][0]['key'])->toBe('ENG')
        ->and(array_column($body['teams'][0]['states'], 'name'))->toBe(array_keys($fix['states']))
        ->and(array_keys($body['teams'][0]['states'][0]))->toBe(['id', 'name', 'type', 'color', 'position'])
        ->and($body['teams'][0]['active_cycle']['number'])->toBe(4)
        ->and($body['teams'][0]['active_cycle']['name'])->toBe('Sprint 4')
        ->and(array_keys($body['teams'][0]['active_cycle']))->toBe(['number', 'name', 'starts_at', 'ends_at'])
        ->and($body['projects'])->toHaveCount(1)
        ->and(array_keys($body['projects'][0]))->toBe([
            'id', 'slug', 'name', 'state', 'color', 'icon', 'team_keys',
            'total_issues', 'completed_issues', 'progress_percent',
        ])
        ->and($body['projects'][0]['slug'])->toBe($project->slug)
        ->and($body['projects'][0]['team_keys'])->toBe(['ENG'])
        ->and($body['projects'][0]['total_issues'])->toBe(2)
        ->and($body['projects'][0]['completed_issues'])->toBe(1)
        ->and($body['projects'][0]['progress_percent'])->toBe(50);
});

it('orders states by position, not by creation', function () {
    $fix = makeWorkspaceFixture();
    WorkflowState::create([
        'team_id' => $fix['team']->id, 'name' => 'Icebox', 'type' => 'backlog', 'color' => '#000000', 'position' => -1,
    ]);

    expect(boardOverview($fix)['teams'][0]['states'][0]['name'])->toBe('Icebox');
});

it('reports a null active cycle when none spans now', function () {
    $fix = makeWorkspaceFixture();
    Cycle::factory()->create([
        'team_id' => $fix['team']->id, 'number' => 1,
        'starts_at' => now()->subDays(20), 'ends_at' => now()->subDays(6), 'completed_at' => null,
    ]);
    Cycle::factory()->create([
        'team_id' => $fix['team']->id, 'number' => 2,
        'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(16), 'completed_at' => null,
    ]);
    Cycle::factory()->create([
        'team_id' => $fix['team']->id, 'number' => 3,
        'starts_at' => now()->subDays(2), 'ends_at' => now()->addDays(5), 'completed_at' => now()->subDay(),
    ]);

    expect(boardOverview($fix)['teams'][0]['active_cycle'])->toBeNull();
});

it('returns zero progress for a project without issues', function () {
    $fix = makeWorkspaceFixture();
    Project::factory()->create(['workspace_id' => $fix['workspace']->id]);

    $project = boardOverview($fix)['projects'][0];

    expect($project['total_issues'])->toBe(0)
        ->and($project['progress_percent'])->toBe(0)
        ->and($project['team_keys'])->toBe([]);
});

it('lists the caller\'s other workspaces and returns only the requested one\'s data', function () {
    $other = makeWorkspaceFixture(['team' => ['key' => 'OPS', 'name' => 'Operations']]);
    $fix = makeWorkspaceFixture();
    // The fixture user also belongs to the other workspace.
    WorkspaceMember::create([
        'workspace_id' => $other['workspace']->id, 'user_id' => $fix['user']->id,
        'role' => 'member', 'joined_at' => now(),
    ]);

    $body = boardOverview($fix, ['workspace_slug' => $fix['workspace']->slug]);

    $active = collect($body['workspaces'])->where('is_active', true)->pluck('slug')->all();

    expect(array_column($body['teams'], 'key'))->toBe(['ENG'])
        ->and($body['workspaces'])->toHaveCount(2)
        ->and(array_keys($body['workspaces'][0]))->toBe(['slug', 'name', 'is_active'])
        ->and($active)->toBe([$fix['workspace']->slug]);
});

it('errors with the usable slugs for a workspace the caller is not in', function () {
    $other = makeWorkspaceFixture(['team' => ['key' => 'OPS']]);
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])
        ->tool(BoardOverview::class, ['workspace_slug' => $other['workspace']->slug])
        ->assertHasErrors()
        ->assertSee($fix['workspace']->slug);
});

it('runs a bounded number of queries however many teams and projects there are', function () {
    $fix = makeWorkspaceFixture();
    foreach (range(1, 5) as $i) {
        $team = Team::factory()->create(['workspace_id' => $fix['workspace']->id, 'key' => 'T'.$i]);
        WorkflowState::create([
            'team_id' => $team->id, 'name' => 'Todo', 'type' => 'unstarted', 'color' => '#111111', 'position' => 0,
        ]);
        Cycle::factory()->create([
            'team_id' => $team->id, 'number' => 1,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'completed_at' => null,
        ]);
    }
    Project::factory()->count(10)->create(['workspace_id' => $fix['workspace']->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $body = boardOverview($fix);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($body['teams'])->toHaveCount(6)
        ->and($body['projects'])->toHaveCount(10)
        ->and($queries)->toBeLessThan(15);
});

it('is registered', function () {
    expect((new WorkspacesModuleManifest)->mcpTools())->toContain(BoardOverview::class);
});
