<?php

declare(strict_types=1);

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Models\ProjectMilestone;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->fix = makeWorkspaceFixture();
    $this->project = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);
    $this->milestone = ProjectMilestone::create([
        'project_id' => $this->project->id,
        'name' => 'M1',
        'description' => 'old',
        'target_date' => '2020-01-01',
        'sort_order' => 0,
    ]);
    $this->as = fn () => $this->actingAs($this->fix['user'])
        ->withSession(['current_workspace_id' => $this->fix['workspace']->id]);
});

it('updates the name, description and target date of a milestone', function () {
    ($this->as)()
        ->from(route('projects.milestones.show', [$this->project->slug, $this->milestone->id]))
        ->patch(route('projects.milestones.update', [$this->project->slug, $this->milestone->id]), [
            'name' => 'M1 · Harness',
            'description' => 'See **LAM-571**',
            'target_date' => '2026-10-01',
        ])
        ->assertRedirect(route('projects.milestones.show', [$this->project->slug, $this->milestone->id]));

    $fresh = $this->milestone->fresh();
    expect($fresh->name)->toBe('M1 · Harness')
        ->and($fresh->description)->toBe('See **LAM-571**')
        ->and($fresh->target_date->toDateString())->toBe('2026-10-01')
        ->and($fresh->completed_at)->toBeNull();

    $activity = ProjectActivity::where('project_id', $this->project->id)->where('kind', 'milestone_updated')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->payload['milestone_name'])->toBe('M1 · Harness');
});

it('marks a milestone as completed and reopens it, recording both in the activity', function () {
    ($this->as)()->patch(route('projects.milestones.update', [$this->project->slug, $this->milestone->id]), [
        'completed' => true,
    ]);

    expect($this->milestone->fresh()->completed_at)->not->toBeNull()
        ->and(ProjectActivity::where('project_id', $this->project->id)->where('kind', 'milestone_completed')->exists())->toBeTrue()
        ->and(ProjectActivity::where('project_id', $this->project->id)->where('kind', 'milestone_updated')->exists())->toBeFalse();

    ($this->as)()->patch(route('projects.milestones.update', [$this->project->slug, $this->milestone->id]), [
        'completed' => false,
    ]);

    expect($this->milestone->fresh()->completed_at)->toBeNull()
        ->and(ProjectActivity::where('project_id', $this->project->id)->where('kind', 'milestone_reopened')->exists())->toBeTrue();
});

it('keeps the original completion date when completed is sent again', function () {
    $this->milestone->update(['completed_at' => '2026-01-15 10:00:00']);

    ($this->as)()->patch(route('projects.milestones.update', [$this->project->slug, $this->milestone->id]), [
        'completed' => true,
    ]);

    expect($this->milestone->fresh()->completed_at->toDateString())->toBe('2026-01-15')
        ->and(ProjectActivity::where('project_id', $this->project->id)->where('kind', 'milestone_completed')->exists())->toBeFalse();
});

it('rejects an empty milestone name', function () {
    ($this->as)()
        ->from(route('projects.milestones.show', [$this->project->slug, $this->milestone->id]))
        ->patch(route('projects.milestones.update', [$this->project->slug, $this->milestone->id]), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($this->milestone->fresh()->name)->toBe('M1');
});

it('returns 404 when the milestone belongs to another project and changes nothing', function () {
    $other = Project::factory()->create(['workspace_id' => $this->fix['workspace']->id]);

    ($this->as)()
        ->patch(route('projects.milestones.update', [$other->slug, $this->milestone->id]), ['name' => 'hijacked'])
        ->assertNotFound();

    expect($this->milestone->fresh()->name)->toBe('M1');
});

it('shows an open milestone whose issues are all done as done, not overdue', function () {
    foreach ([1, 2] as $_) {
        makeIssue($this->fix['team'], $this->fix['workspace'], $this->fix['states']['Done'], [
            'project_id' => $this->project->id,
            'project_milestone_id' => $this->milestone->id,
        ]);
    }

    ($this->as)()
        ->get(route('projects.milestones.show', [$this->project->slug, $this->milestone->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('milestone.status', 'done')
            ->where('milestone.days_left', fn ($days) => is_int($days) && $days < 0)
        );
});
