<?php

declare(strict_types=1);

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Label;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('CycleDetailController::show', function () {
    it('returns 404 when team query string is missing', function () {
        $cycle = Cycle::factory()->create(['team_id' => $this->team->id, 'number' => 1]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.show', ['number' => $cycle->number]));

        $response->assertNotFound();
    });

    it('returns 404 when team key is unknown for the workspace', function () {
        Cycle::factory()->create(['team_id' => $this->team->id, 'number' => 1]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.show', ['number' => 1, 'team' => 'NOPE']));

        $response->assertNotFound();
    });

    it('returns 404 when no cycle has that number for the team', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.show', ['number' => 999, 'team' => 'ENG']));

        $response->assertNotFound();
    });

    it('returns 200 and computes progress totals', function () {
        $cycle = Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(5),
        ]);

        // 1 done, 1 started, 2 backlog → total 4, completed 1, started 1, percent=25
        makeIssue($this->team, $this->workspace, $this->states['Done'], ['cycle_id' => $cycle->id]);
        makeIssue($this->team, $this->workspace, $this->states['In Progress'], ['cycle_id' => $cycle->id]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], ['cycle_id' => $cycle->id]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], ['cycle_id' => $cycle->id]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.show', ['number' => 1, 'team' => 'ENG']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('progress.total', 4)
            ->where('progress.completed', 1)
            ->where('progress.started', 1)
            ->where('progress.percent', 25)
            ->where('cycle.status', 'current')
            ->has('cycle.weekdays_left')
        );
    });

    it('breaks down progress by assignee, label, priority, project', function () {
        $cycle = Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(5),
        ]);

        $project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
        $bug = Label::factory()->create(['team_id' => $this->team->id]);

        $i1 = makeIssue($this->team, $this->workspace, $this->states['Done'], [
            'cycle_id' => $cycle->id,
            'priority' => 1,
            'assignee_user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);
        $i1->labels()->attach($bug->id);

        $i2 = makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'cycle_id' => $cycle->id,
            'priority' => 2,
            'assignee_user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);
        $i2->labels()->attach($bug->id);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.show', ['number' => 1, 'team' => 'ENG']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('assignees', 1)
            ->has('labels_breakdown', 1)
            ->where('labels_breakdown.0.total', 2)
            ->has('priority_breakdown', 2)
            ->has('projects_breakdown', 1)
        );
    });

    it('reports cycle status as upcoming for future starts_at', function () {
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(15),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.show', ['number' => 1, 'team' => 'ENG']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('cycle.status', 'upcoming'));
    });

    it('reports cycle status as completed when completed_at is set', function () {
        Cycle::factory()->create([
            'team_id' => $this->team->id,
            'number' => 1,
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(10),
            'completed_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('cycles.show', ['number' => 1, 'team' => 'ENG']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('cycle.status', 'completed'));
    });
});
