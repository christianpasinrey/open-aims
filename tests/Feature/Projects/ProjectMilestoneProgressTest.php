<?php

declare(strict_types=1);

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use Inertia\Testing\AssertableInertia;

it('shows real per-milestone progress on the project page', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $alpha = ProjectMilestone::create(['project_id' => $project->id, 'name' => 'Alpha', 'sort_order' => 0]);
    ProjectMilestone::create(['project_id' => $project->id, 'name' => 'Beta', 'sort_order' => 1]);

    foreach (['Done', 'Todo'] as $state) {
        makeIssue($fix['team'], $fix['workspace'], $fix['states'][$state], [
            'project_id' => $project->id,
            'project_milestone_id' => $alpha->id,
        ]);
    }

    $this->actingAs($fix['user'])
        ->withSession(['current_workspace_id' => $fix['workspace']->id])
        ->get(route('projects.show', $project->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.milestones.0.name', 'Alpha')
            ->where('project.milestones.0.issue_count', 2)
            ->where('project.milestones.0.completed_count', 1)
            ->where('project.milestones.0.percent', 50)
            ->where('project.milestones.1.issue_count', 0)
            ->where('project.milestones.1.percent', 0)
        );
});
