<?php

declare(strict_types=1);

use App\Modules\Projects\Models\Project;
use Inertia\Testing\AssertableInertia;

it('renders the map page with its filters and project options only', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id, 'name' => 'Roadmap']);
    $outsider = makeWorkspaceFixture();
    Project::factory()->create(['workspace_id' => $outsider['workspace']->id, 'name' => 'Foreign']);

    $this->actingAs($fix['user'])
        ->withSession(['current_workspace_id' => $fix['workspace']->id])
        ->get(route('graphs.map.page', ['project' => $project->slug, 'context' => 'backend']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('graphs/Map')
            ->where('filters.project', $project->slug)
            ->where('filters.context', 'backend')
            ->where('filters.milestone', null)
            ->has('projects', 1)
            ->where('projects.0.name', 'Roadmap')
            ->has('contexts', 8)
        );
});

it('opens the graphs tab of a project', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);

    $this->actingAs($fix['user'])
        ->withSession(['current_workspace_id' => $fix['workspace']->id])
        ->get(route('projects.show', ['slug' => $project->slug, 'tab' => 'graphs']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'graphs'));
});
