<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceJoinRequest;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\Notifications\WorkspaceJoinDecisionNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->requester = User::factory()->create(['email_verified_at' => now()]);
    $this->jr = WorkspaceJoinRequest::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->requester->id,
        'status' => 'pending',
    ]);
});

it('lets an owner approve a request: membership + notify', function () {
    Notification::fake();
    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post("/workspace/requests/{$this->jr->id}/approve")
        ->assertRedirect();
    expect(WorkspaceMember::where('workspace_id', $this->workspace->id)->where('user_id', $this->requester->id)->where('role', 'member')->exists())->toBeTrue()
        ->and($this->jr->fresh()->status)->toBe('approved')
        ->and($this->jr->fresh()->responded_by_user_id)->toBe($this->owner->id);
    Notification::assertSentTo($this->requester, WorkspaceJoinDecisionNotification::class);
});

it('lets an owner reject a request: no membership + notify', function () {
    Notification::fake();
    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post("/workspace/requests/{$this->jr->id}/reject")
        ->assertRedirect();
    expect(WorkspaceMember::where('workspace_id', $this->workspace->id)->where('user_id', $this->requester->id)->exists())->toBeFalse()
        ->and($this->jr->fresh()->status)->toBe('rejected')
        ->and($this->jr->fresh()->responded_by_user_id)->toBe($this->owner->id);
    Notification::assertSentTo($this->requester, WorkspaceJoinDecisionNotification::class);
});

it('forbids the owner of another workspace from approving', function () {
    $otherFix = makeWorkspaceFixture();
    $this->actingAs($otherFix['user'])
        ->withSession(['current_workspace_id' => $otherFix['workspace']->id])
        ->post("/workspace/requests/{$this->jr->id}/approve")
        ->assertForbidden();
});

it('forbids a non-admin from approving', function () {
    $member = User::factory()->create(['email_verified_at' => now()]);
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);
    $this->actingAs($member)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post("/workspace/requests/{$this->jr->id}/approve")
        ->assertForbidden();
});

it('lists pending requests as JSON for an owner', function () {
    $res = $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->getJson('/workspace/requests');
    $res->assertOk();
    $data = collect($res->json('data'));
    expect($data->pluck('id'))->toContain($this->jr->id)
        ->and($data->firstWhere('id', $this->jr->id)['user']['email'])->toBe($this->requester->email);
});

it('returns empty pending list for a non-admin', function () {
    $member = User::factory()->create(['email_verified_at' => now()]);
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);
    $this->actingAs($member)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->getJson('/workspace/requests')
        ->assertOk()
        ->assertJson(['data' => []]);
});
