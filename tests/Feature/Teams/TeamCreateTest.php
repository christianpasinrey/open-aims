<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\WorkspaceMember;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
});

it('lets an owner create a team with default states', function () {
    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('teams.store'), ['name' => 'Design'])
        ->assertRedirect();

    $team = Team::where('workspace_id', $this->workspace->id)->where('name', 'Design')->first();
    expect($team)->not->toBeNull()->and($team->key)->toBe('DES');
    expect(WorkflowState::where('team_id', $team->id)->count())->toBe(6);
});

it('rejects an explicit key that already exists in the workspace', function () {
    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->from('/workspace/teams')
        ->post(route('teams.store'), ['name' => 'Another', 'key' => 'ENG'])
        ->assertSessionHasErrors('key');
});

it('forbids a plain member from creating a team', function () {
    $member = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($member)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('teams.store'), ['name' => 'Nope'])
        ->assertForbidden();
});

it('lets an admin create a team', function () {
    $admin = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $admin->id, 'role' => 'admin', 'joined_at' => now()]);

    $this->actingAs($admin)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('teams.store'), ['name' => 'Ops'])
        ->assertRedirect();

    expect(Team::where('workspace_id', $this->workspace->id)->where('name', 'Ops')->exists())->toBeTrue();
});
