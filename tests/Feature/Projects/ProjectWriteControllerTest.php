<?php

declare(strict_types=1);

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
});

describe('ProjectWriteController::store', function () {
    it('creates a project with a unique slug and attaches selected teams', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('projects.store'), [
                'name' => 'My Project',
                'team_keys' => ['ENG'],
            ]);

        $response->assertRedirect();
        $project = Project::query()->where('name', 'My Project')->first();

        expect($project)->not->toBeNull();
        expect($project->slug)->toStartWith('my-project-');
        expect($project->teams->pluck('id')->all())->toContain($this->team->id);
    });

    it('rejects when name is missing', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('projects.index'))
            ->post(route('projects.store'), []);

        $response->assertSessionHasErrors('name');
    });

    it('sets completed_at when state is completed', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('projects.store'), [
                'name' => 'Done Project',
                'state' => 'completed',
            ]);

        $project = Project::where('name', 'Done Project')->first();
        expect($project->completed_at)->not->toBeNull();
    });
});

describe('ProjectWriteController::update', function () {
    it('partially updates fields via PATCH', function () {
        $project = Project::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Old',
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('projects.show', ['slug' => $project->slug]))
            ->patch(route('projects.update', ['slug' => $project->slug]), [
                'name' => 'New',
            ]);

        expect($project->fresh()->name)->toBe('New');
    });

    it('auto-manages completed_at on state transition to completed', function () {
        $project = Project::factory()->create([
            'workspace_id' => $this->workspace->id,
            'state' => 'started',
            'completed_at' => null,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('projects.show', ['slug' => $project->slug]))
            ->patch(route('projects.update', ['slug' => $project->slug]), [
                'state' => 'completed',
            ]);

        expect($project->fresh()->completed_at)->not->toBeNull();
    });

    it('clears completed_at when state moves out of completed', function () {
        $project = Project::factory()->create([
            'workspace_id' => $this->workspace->id,
            'state' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('projects.show', ['slug' => $project->slug]))
            ->patch(route('projects.update', ['slug' => $project->slug]), [
                'state' => 'started',
            ]);

        expect($project->fresh()->completed_at)->toBeNull();
    });
});

describe('ProjectWriteController::storeMilestone', function () {
    it('creates a milestone for the given project', function () {
        $project = Project::factory()->create(['workspace_id' => $this->workspace->id]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('projects.show', ['slug' => $project->slug]))
            ->post(route('projects.milestones.store', ['slug' => $project->slug]), [
                'name' => 'M1',
            ]);

        expect(ProjectMilestone::where('project_id', $project->id)->where('name', 'M1')->exists())
            ->toBeTrue();
    });

    it('returns 404 for a missing project slug', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('projects.milestones.store', ['slug' => 'nope']), [
                'name' => 'M1',
            ]);

        $response->assertNotFound();
    });
});
