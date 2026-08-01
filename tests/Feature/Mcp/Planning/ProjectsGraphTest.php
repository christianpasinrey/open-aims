<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Initiatives\Models\Initiative;
use App\Modules\Projects\Mcp\Tools\ProjectsAddMilestone;
use App\Modules\Projects\Mcp\Tools\ProjectsCreate;
use App\Modules\Projects\Mcp\Tools\ProjectsGet;
use App\Modules\Projects\Models\Project;

/** Create a project through the tool and return the model. */
function makePlannedProject(array $fix, string $name = 'Graph project'): Project
{
    AimsServer::actingAs($fix['user'])->tool(ProjectsCreate::class, [
        'name' => $name,
        'team_keys' => [$fix['team']->key],
        'goal' => 'Deliver the graph.',
        'scope' => 'In: the graph. Out: everything else.',
        'plan_content' => 'plan body',
        'plan_format' => 'md',
    ])->assertOk();

    return Project::where('name', $name)->firstOrFail();
}

it('returns milestones with their dates from projects-get', function () {
    $fix = makeWorkspaceFixture();
    $project = makePlannedProject($fix);

    AimsServer::actingAs($fix['user'])->tool(ProjectsAddMilestone::class, [
        'project_slug' => $project->slug,
        'name' => 'Alpha cut',
        'description' => 'Feature complete',
        'target_date' => '2026-09-30',
    ])->assertOk();

    AimsServer::actingAs($fix['user'])->tool(ProjectsGet::class, [
        'slug' => $project->slug,
    ])->assertOk()->assertSee(['Alpha cut', 'Feature complete', '2026-09-30']);
});

it('returns the issue breakdown by workflow state from projects-get', function () {
    $fix = makeWorkspaceFixture();
    $project = makePlannedProject($fix);

    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $project->id, 'title' => 'todo one',
    ]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $project->id, 'title' => 'todo two',
    ]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Done'], [
        'project_id' => $project->id, 'title' => 'done one',
    ]);

    $response = AimsServer::actingAs($fix['user'])->tool(ProjectsGet::class, [
        'slug' => $project->slug,
    ])->assertOk();

    $response->assertSee(['issue_breakdown', 'Todo', 'Done', 'unstarted', 'completed']);

    $project->refresh();
    expect($project->issues()->count())->toBe(3);
});

it('reports the initiative a project rolls up to', function () {
    $fix = makeWorkspaceFixture();
    $project = makePlannedProject($fix);

    $initiative = Initiative::create([
        'workspace_id' => $fix['workspace']->id,
        'name' => 'Retention push',
        'slug' => 'retention-push',
        'state' => 'active',
        'target_date' => '2026-12-31',
    ]);
    $initiative->projects()->attach($project->id, ['sort_order' => 0]);

    AimsServer::actingAs($fix['user'])->tool(ProjectsGet::class, [
        'slug' => $project->slug,
    ])->assertOk()->assertSee(['Retention push', 'retention-push', '2026-12-31']);
});

it('returns a null initiative when the project belongs to none', function () {
    $fix = makeWorkspaceFixture();
    $project = makePlannedProject($fix);

    AimsServer::actingAs($fix['user'])->tool(ProjectsGet::class, [
        'slug' => $project->slug,
    ])->assertOk()->assertSee('"initiative":null');
});
