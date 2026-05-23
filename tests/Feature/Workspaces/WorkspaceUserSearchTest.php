<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
});

it('lets an owner search registered users by name or email, excluding current members', function () {
    $alice = User::factory()->create(['name' => 'Alice Anderson', 'email' => 'alice@example.com']);
    $bob = User::factory()->create(['name' => 'Bob Brown', 'email' => 'bob@example.com']);
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $bob->id, 'role' => 'member', 'joined_at' => now()]);

    $res = $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->getJson('/workspace/users/search?q=al');

    $res->assertOk();
    $emails = collect($res->json('data'))->pluck('email')->all();
    expect($emails)->toContain('alice@example.com')->and($emails)->not->toContain('bob@example.com');
});

it('flags users with a pending invitation', function () {
    User::factory()->create(['name' => 'Carol', 'email' => 'carol@example.com']);
    WorkspaceInvitation::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'carol@example.com',
        'role' => 'member',
        'token' => str_repeat('c', 64),
        'invited_by_user_id' => $this->owner->id,
        'expires_at' => now()->addDay(),
    ]);

    $res = $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->getJson('/workspace/users/search?q=carol');

    expect(collect($res->json('data'))->firstWhere('email', 'carol@example.com')['invited'])->toBeTrue();
});

it('returns empty for a short query', function () {
    $res = $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->getJson('/workspace/users/search?q=a');
    expect($res->json('data'))->toBe([]);
});

it('forbids a non-admin from searching users', function () {
    $member = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($member)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->getJson('/workspace/users/search?q=alice')
        ->assertForbidden();
});
