<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Team;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
});

describe('ProjectListController::index', function () {
    it('returns an empty list for a workspace with no projects', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('projects/Index')
            ->where('projects', [])
        );
    });

    it('lists projects in the workspace', function () {
        Project::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 3));
    });

    it('filters projects by team key', function () {
        $teamA = $this->team;
        $teamB = Team::factory()->create(['workspace_id' => $this->workspace->id, 'key' => 'OPS']);

        $a = Project::factory()->create(['workspace_id' => $this->workspace->id]);
        $b = Project::factory()->create(['workspace_id' => $this->workspace->id]);
        $a->teams()->attach($teamA->id);
        $b->teams()->attach($teamB->id);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index', ['team' => 'OPS']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 1));
    });

    it('filters by project status', function () {
        Project::factory()->create(['workspace_id' => $this->workspace->id, 'state' => 'started']);
        Project::factory()->create(['workspace_id' => $this->workspace->id, 'state' => 'completed']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index', ['status' => 'started']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 1));
    });

    it('filters by lead user id', function () {
        $lead = User::factory()->create();
        Project::factory()->create(['workspace_id' => $this->workspace->id, 'lead_user_id' => $lead->id]);
        Project::factory()->create(['workspace_id' => $this->workspace->id, 'lead_user_id' => null]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index', ['lead' => (string) $lead->id]));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 1));
    });

    it('respects sort=name (alphabetic)', function () {
        Project::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Banana']);
        Project::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Apple']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index', ['sort' => 'name']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('projects.0.name', 'Apple')
            ->where('projects.1.name', 'Banana')
        );
    });

    it('respects sort=status (started → planned → ...)', function () {
        Project::factory()->create(['workspace_id' => $this->workspace->id, 'state' => 'planned', 'name' => 'P']);
        Project::factory()->create(['workspace_id' => $this->workspace->id, 'state' => 'started', 'name' => 'S']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index', ['sort' => 'status']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('projects.0.state', 'started')
            ->where('projects.1.state', 'planned')
        );
    });

    it('passes group filter through unchanged when valid', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index', ['group' => 'lead']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('filters.group', 'lead'));
    });

    it('falls back to default group=none when input is invalid', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('projects.index', ['group' => 'bogus']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('filters.group', 'none'));
    });
});
