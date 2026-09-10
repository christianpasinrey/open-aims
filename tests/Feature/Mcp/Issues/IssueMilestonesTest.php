<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Issues\Mcp\Tools\IssuesCreate;
use App\Modules\Issues\Mcp\Tools\IssuesGet;
use App\Modules\Issues\Mcp\Tools\IssuesList;
use App\Modules\Issues\Mcp\Tools\IssuesUpdate;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Projects\Mcp\Tools\ProjectsCreate;
use App\Modules\Projects\Mcp\Tools\ProjectsGet;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;

if (! function_exists('makeMilestoneProject')) {
    /** Create a project through the MCP tool and return the model. */
    function makeMilestoneProject(array $fix, string $name): Project
    {
        AimsServer::actingAs($fix['user'])->tool(ProjectsCreate::class, [
            'name' => $name,
            'team_keys' => [$fix['team']->key],
            'goal' => 'Ship it.',
            'scope' => 'In: it. Out: the rest.',
            'skip_plan' => true,
        ])->assertOk();

        return Project::where('name', $name)->firstOrFail();
    }
}

if (! function_exists('makeMilestone')) {
    function makeMilestone(Project $project, string $name, int $sort = 0): ProjectMilestone
    {
        return ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => $name,
            'sort_order' => $sort,
        ]);
    }
}

it('links an issue to a milestone of its project by name through issues-update', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    $alpha = makeMilestone($project, 'Alpha');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['project_id' => $project->id]);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'milestone' => 'alpha',
        'skip_plan' => true,
    ])->assertOk();

    expect($issue->fresh()->project_milestone_id)->toBe($alpha->id);

    AimsServer::actingAs($fix['user'])->tool(IssuesGet::class, [
        'identifier' => 'ENG-'.$issue->number,
    ])->assertOk()->assertSee('"milestone":{"id":'.$alpha->id.',"name":"Alpha"');
});

it('links an issue to a milestone by numeric id', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    $beta = makeMilestone($project, 'Beta');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['project_id' => $project->id]);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'milestone' => (string) $beta->id,
        'skip_plan' => true,
    ])->assertOk();

    expect($issue->fresh()->project_milestone_id)->toBe($beta->id);
});

it('creates an issue already linked to a milestone', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    $alpha = makeMilestone($project, 'Alpha');

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'born in a milestone',
        'project_slug' => $project->slug,
        'milestone' => 'Alpha',
        'skip_scrum' => true,
        'skip_plan' => true,
    ])->assertOk();

    $issue = Issue::where('title', 'born in a milestone')->firstOrFail();
    expect($issue->project_milestone_id)->toBe($alpha->id);
});

it('rejects a milestone that belongs to another project and saves nothing', function () {
    $fix = makeWorkspaceFixture();
    $roadmap = makeMilestoneProject($fix, 'Roadmap');
    $other = makeMilestoneProject($fix, 'Other');
    makeMilestone($other, 'Gamma');
    makeMilestone($roadmap, 'Alpha');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $roadmap->id,
        'title' => 'untouched',
    ]);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'milestone' => 'Gamma',
        'title' => 'should not be saved',
        'skip_plan' => true,
    ])->assertHasErrors(["Milestone 'Gamma' not found in project 'Roadmap'", 'Alpha']);

    $fresh = $issue->fresh();
    expect($fresh->project_milestone_id)->toBeNull()
        ->and($fresh->title)->toBe('untouched');
});

it('rejects a milestone on an issue without a project', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'milestone' => 'Alpha',
        'skip_plan' => true,
    ])->assertHasErrors(['has no project']);
});

it('rejects a milestone name shared by two milestones of the same project', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    makeMilestone($project, 'Alpha');
    makeMilestone($project, 'alpha');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['project_id' => $project->id]);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'milestone' => 'Alpha',
        'skip_plan' => true,
    ])->assertHasErrors(['ambiguous']);
});

it('unlinks the milestone when null is passed', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    $alpha = makeMilestone($project, 'Alpha');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $project->id,
        'project_milestone_id' => $alpha->id,
    ]);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'milestone' => null,
        'skip_plan' => true,
    ])->assertOk();

    expect($issue->fresh()->project_milestone_id)->toBeNull();
});

it('drops the milestone when the issue moves to another project', function () {
    $fix = makeWorkspaceFixture();
    $roadmap = makeMilestoneProject($fix, 'Roadmap');
    $other = makeMilestoneProject($fix, 'Other');
    $alpha = makeMilestone($roadmap, 'Alpha');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $roadmap->id,
        'project_milestone_id' => $alpha->id,
    ]);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'project_slug' => $other->slug,
        'skip_plan' => true,
    ])->assertOk();

    $fresh = $issue->fresh();
    expect($fresh->project_id)->toBe($other->id)
        ->and($fresh->project_milestone_id)->toBeNull();
});

it('moves an issue to another project and into one of its milestones in one call', function () {
    $fix = makeWorkspaceFixture();
    $roadmap = makeMilestoneProject($fix, 'Roadmap');
    $other = makeMilestoneProject($fix, 'Other');
    $alpha = makeMilestone($roadmap, 'Alpha');
    $gamma = makeMilestone($other, 'Gamma');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $roadmap->id,
        'project_milestone_id' => $alpha->id,
    ]);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'project_slug' => $other->slug,
        'milestone' => 'Gamma',
        'skip_plan' => true,
    ])->assertOk();

    expect($issue->fresh()->project_milestone_id)->toBe($gamma->id);
});

it('keeps issues but clears their milestone when the milestone is deleted', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    $alpha = makeMilestone($project, 'Alpha');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $project->id,
        'project_milestone_id' => $alpha->id,
    ]);

    $alpha->delete();

    $fresh = $issue->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->project_milestone_id)->toBeNull();
});

it('reports per-milestone progress from projects-get', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    $alpha = makeMilestone($project, 'Alpha');
    makeMilestone($project, 'Beta', 1);

    foreach (['Done', 'Done', 'Done', 'Todo'] as $state) {
        makeIssue($fix['team'], $fix['workspace'], $fix['states'][$state], [
            'project_id' => $project->id,
            'project_milestone_id' => $alpha->id,
        ]);
    }
    // Outside any milestone: counts for the project, not for Alpha.
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['project_id' => $project->id]);

    AimsServer::actingAs($fix['user'])->tool(ProjectsGet::class, [
        'slug' => $project->slug,
    ])->assertOk()->assertSee([
        '"name":"Alpha"',
        '"total_issues":4,"completed_issues":3,"progress_percent":75',
        '"total_issues":0,"completed_issues":0,"progress_percent":0',
    ]);
});

it('filters issues-list by milestone', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    $alpha = makeMilestone($project, 'Alpha');
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $project->id,
        'project_milestone_id' => $alpha->id,
        'title' => 'inside alpha',
    ]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $project->id,
        'title' => 'outside alpha',
    ]);

    AimsServer::actingAs($fix['user'])->tool(IssuesList::class, [
        'project' => $project->slug,
        'milestone' => 'Alpha',
    ])->assertOk()
        ->assertSee(['"count":1', 'inside alpha', '"milestone":"Alpha"'])
        ->assertDontSee('outside alpha');
});

it('records milestone changes in the issue activity', function () {
    $fix = makeWorkspaceFixture();
    $project = makeMilestoneProject($fix, 'Roadmap');
    makeMilestone($project, 'Alpha');
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['project_id' => $project->id]);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'milestone' => 'Alpha',
        'skip_plan' => true,
    ])->assertOk();

    $activity = IssueActivity::where('issue_id', $issue->id)->where('kind', 'milestone_set')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->payload['milestone_name'])->toBe('Alpha');

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'milestone' => null,
        'skip_plan' => true,
    ])->assertOk();

    expect(IssueActivity::where('issue_id', $issue->id)->where('kind', 'milestone_unset')->exists())->toBeTrue();
});
