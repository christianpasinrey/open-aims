<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceJoinRequest;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\Notifications\WorkspaceJoinRequestNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
});

it('stores a pending join request with relations', function () {
    $u = User::factory()->create();
    $jr = WorkspaceJoinRequest::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $u->id,
        'status' => 'pending',
    ]);

    expect($jr->fresh()->status)->toBe('pending')
        ->and($jr->workspace->id)->toBe($this->workspace->id)
        ->and($jr->user->id)->toBe($u->id);
});

function openWorkspace(string $policy): Workspace
{
    return Workspace::create([
        'name' => ucfirst($policy).' WS',
        'slug' => $policy.'-ws-'.uniqid(),
        'owner_user_id' => User::factory()->create()->id,
        'join_policy' => $policy,
    ]);
}

it('joins an open workspace instantly as member', function () {
    $ws = openWorkspace('open');
    $u = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($u)->post("/workspaces/{$ws->slug}/join")
        ->assertRedirect()
        ->assertSessionHas('current_workspace_id', $ws->id);
    expect(WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $u->id)->where('role', 'member')->exists())->toBeTrue();
});

it('creates a pending request and notifies owner/admins for a request workspace', function () {
    Notification::fake();
    $ws = openWorkspace('request');
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $ws->owner_user_id, 'role' => 'owner', 'joined_at' => now()]);
    $owner = User::find($ws->owner_user_id);
    $u = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($u)->post("/workspaces/{$ws->slug}/join")->assertRedirect();
    expect(WorkspaceJoinRequest::where('workspace_id', $ws->id)->where('user_id', $u->id)->where('status', 'pending')->exists())->toBeTrue();
    expect(WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $u->id)->exists())->toBeFalse();
    Notification::assertSentTo($owner, WorkspaceJoinRequestNotification::class);
});

it('forbids joining a private workspace', function () {
    $ws = openWorkspace('private');
    $u = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($u)->post("/workspaces/{$ws->slug}/join")->assertForbidden();
});

it('is a no-op when already a member', function () {
    $ws = openWorkspace('open');
    $u = User::factory()->create(['email_verified_at' => now()]);
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $u->id, 'role' => 'member', 'joined_at' => now()]);
    $this->actingAs($u)->post("/workspaces/{$ws->slug}/join")
        ->assertRedirect()
        ->assertSessionHas('current_workspace_id', $ws->id);
    expect(WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $u->id)->count())->toBe(1);
});

it('returns 404 for a non-existent workspace slug', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($u)->post('/workspaces/does-not-exist/join')->assertNotFound();
});
