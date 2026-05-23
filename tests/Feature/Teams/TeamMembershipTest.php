<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Teams\Models\TeamMember;
use App\Modules\Workspaces\Models\WorkspaceMember;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->member = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $this->member->id, 'role' => 'member', 'joined_at' => now()]);
});

it('lets an owner add a workspace member to the team with a role', function () {
    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('teams.members.store', ['key' => 'ENG']), ['user_id' => $this->member->id, 'role' => 'lead'])
        ->assertRedirect();

    expect(TeamMember::where('team_id', $this->team->id)->where('user_id', $this->member->id)->where('role', 'lead')->exists())->toBeTrue();
});

it('rejects adding a user who is not a workspace member', function () {
    $outsider = User::factory()->create();

    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->from('/teams/ENG/members')
        ->post(route('teams.members.store', ['key' => 'ENG']), ['user_id' => $outsider->id, 'role' => 'member'])
        ->assertSessionHasErrors('user_id');

    expect(TeamMember::where('team_id', $this->team->id)->where('user_id', $outsider->id)->exists())->toBeFalse();
});

it('is idempotent when adding an existing team member', function () {
    TeamMember::create(['team_id' => $this->team->id, 'user_id' => $this->member->id, 'role' => 'member']);

    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('teams.members.store', ['key' => 'ENG']), ['user_id' => $this->member->id, 'role' => 'lead']);

    expect(TeamMember::where('team_id', $this->team->id)->where('user_id', $this->member->id)->count())->toBe(1);
});

it('lets an owner remove a team member', function () {
    TeamMember::create(['team_id' => $this->team->id, 'user_id' => $this->member->id, 'role' => 'member']);

    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->delete(route('teams.members.destroy', ['key' => 'ENG', 'userId' => $this->member->id]))
        ->assertRedirect();

    expect(TeamMember::where('team_id', $this->team->id)->where('user_id', $this->member->id)->exists())->toBeFalse();
});

it('forbids a plain member from managing team membership', function () {
    $plain = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $plain->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($plain)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('teams.members.store', ['key' => 'ENG']), ['user_id' => $this->member->id, 'role' => 'member'])
        ->assertForbidden();
});

it('forbids a plain member from removing a team member', function () {
    TeamMember::create(['team_id' => $this->team->id, 'user_id' => $this->member->id, 'role' => 'member']);
    $plain = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $plain->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($plain)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->delete(route('teams.members.destroy', ['key' => 'ENG', 'userId' => $this->member->id]))
        ->assertForbidden();

    expect(TeamMember::where('team_id', $this->team->id)->where('user_id', $this->member->id)->exists())->toBeTrue();
});
