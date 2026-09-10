<?php

declare(strict_types=1);

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use Inertia\Testing\AssertableInertia;

if (! function_exists('milestoneOf')) {
    function milestoneOf(Project $project, string $name, array $attributes = []): ProjectMilestone
    {
        return ProjectMilestone::create(array_merge([
            'project_id' => $project->id,
            'name' => $name,
            'sort_order' => 0,
        ], $attributes));
    }
}

it('shows a milestone with its progress, state breakdown and only its issues', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $alpha = milestoneOf($project, 'Alpha', ['description' => 'First cut', 'target_date' => '2099-12-01']);
    $beta = milestoneOf($project, 'Beta', ['sort_order' => 1]);

    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Done'], [
        'project_id' => $project->id, 'project_milestone_id' => $alpha->id, 'title' => 'shipped',
    ]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $project->id, 'project_milestone_id' => $alpha->id, 'title' => 'pending',
    ]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['In Progress'], [
        'project_id' => $project->id, 'project_milestone_id' => $beta->id, 'title' => 'elsewhere',
    ]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'project_id' => $project->id, 'title' => 'loose',
    ]);

    $this->actingAs($fix['user'])
        ->withSession(['current_workspace_id' => $fix['workspace']->id])
        ->get(route('projects.milestones.show', [$project->slug, $alpha->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('projects/milestones/Show')
            ->where('project.slug', $project->slug)
            ->where('milestone.id', $alpha->id)
            ->where('milestone.name', 'Alpha')
            ->where('milestone.description', 'First cut')
            ->where('milestone.target_date', '2099-12-01')
            ->where('milestone.status', 'on_track')
            ->where('progress.total', 2)
            ->where('progress.completed', 1)
            ->where('progress.percent', 50)
            ->has('issues', 2)
            ->where('issues', fn ($issues) => collect($issues)->pluck('title')->sort()->values()->all() === ['pending', 'shipped'])
            ->has('state_breakdown', 2)
            ->has('milestones', 2)
        );
});

it('marks a milestone past its target date as overdue and a closed one as completed', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $late = milestoneOf($project, 'Late', ['target_date' => '2020-01-01']);
    $closed = milestoneOf($project, 'Closed', ['target_date' => '2020-01-01', 'completed_at' => now()]);

    $as = $this->actingAs($fix['user'])->withSession(['current_workspace_id' => $fix['workspace']->id]);

    $as->get(route('projects.milestones.show', [$project->slug, $late->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('milestone.status', 'overdue'));

    $as->get(route('projects.milestones.show', [$project->slug, $closed->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('milestone.status', 'completed'));
});

it('returns 404 for a milestone that belongs to another project', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $other = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $foreign = milestoneOf($other, 'Foreign');

    $this->actingAs($fix['user'])
        ->withSession(['current_workspace_id' => $fix['workspace']->id])
        ->get(route('projects.milestones.show', [$project->slug, $foreign->id]))
        ->assertNotFound();
});

it('returns 404 for a project in another workspace', function () {
    $outsider = makeWorkspaceFixture();
    $outsiderProject = Project::factory()->create(['workspace_id' => $outsider['workspace']->id]);
    $outsiderMilestone = milestoneOf($outsiderProject, 'Secret');

    $fix = makeWorkspaceFixture();

    $this->actingAs($fix['user'])
        ->withSession(['current_workspace_id' => $fix['workspace']->id])
        ->get(route('projects.milestones.show', [$outsiderProject->slug, $outsiderMilestone->id]))
        ->assertNotFound();
});

it('opens the milestones tab of the project page', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $alpha = milestoneOf($project, 'Alpha', ['completed_at' => now()]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Done'], [
        'project_id' => $project->id, 'project_milestone_id' => $alpha->id,
    ]);

    $this->actingAs($fix['user'])
        ->withSession(['current_workspace_id' => $fix['workspace']->id])
        ->get(route('projects.show', ['slug' => $project->slug, 'tab' => 'milestones']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tab', 'milestones')
            ->where('project.milestones.0.name', 'Alpha')
            ->where('project.milestones.0.completed_count', 1)
            ->where('project.milestones.0.completed_at', fn ($value) => is_string($value) && $value !== '')
        );
});
