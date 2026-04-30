<?php

declare(strict_types=1);

use App\Modules\Projects\Models\Project;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('ProjectDetailController::show', function () {
    it('returns 200 for an existing project', function () {
        $project = Project::factory()->create([
            'workspace_id' => $this->workspace->id,
            'slug' => 'demo-project',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.show', ['slug' => $project->slug]));

        $response->assertOk();
    });

    it('returns 404 for a missing project', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.show', ['slug' => 'does-not-exist']));

        $response->assertNotFound();
    });

    it('defaults the tab parameter to overview', function () {
        $project = Project::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.show', ['slug' => $project->slug]));

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'overview'));
    });

    it('issues tab returns project issues, states, and progress payload', function () {
        $project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
        $project->teams()->attach($this->team->id);

        makeIssue($this->team, $this->workspace, $this->states['Done'], [
            'project_id' => $project->id,
        ]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'project_id' => $project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.show', ['slug' => $project->slug, 'tab' => 'issues']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tab', 'issues')
            ->has('issues', 2)
            ->where('progress.total', 2)
            ->where('progress.completed', 1)
            ->where('progress.percent', 50)
        );
    });
});
