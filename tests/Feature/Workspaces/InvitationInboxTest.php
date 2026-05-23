<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Str;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
});

it('treats a declined invitation as not acceptable', function () {
    $inv = WorkspaceInvitation::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'x@example.com',
        'role' => 'member',
        'token' => str_repeat('a', 64),
        'invited_by_user_id' => $this->owner->id,
        'expires_at' => now()->addDay(),
        'declined_at' => now(),
    ]);

    expect($inv->isAcceptable())->toBeFalse();
});

function inviteFor(int $workspaceId, int $inviterId, string $email, array $overrides = []): WorkspaceInvitation
{
    return WorkspaceInvitation::create(array_merge([
        'workspace_id' => $workspaceId,
        'email' => $email,
        'role' => 'member',
        'token' => Str::random(64),
        'invited_by_user_id' => $inviterId,
        'expires_at' => now()->addDays(3),
    ], $overrides));
}

it('lists my pending invitations across workspaces by email', function () {
    $u = User::factory()->create(['email' => 'invitee@example.com', 'email_verified_at' => now()]);
    inviteFor($this->workspace->id, $this->owner->id, 'invitee@example.com');
    inviteFor($this->workspace->id, $this->owner->id, 'invitee@example.com', ['token' => str_repeat('q', 64), 'accepted_at' => now()]);

    $res = $this->actingAs($u)->getJson('/invitations/pending');
    $res->assertOk();
    $data = collect($res->json('data'));
    expect($data)->toHaveCount(1)
        ->and($data->first()['workspace']['name'])->toBe($this->workspace->name);
});

it('accepts an invitation addressed to me: membership + accepted_at', function () {
    $u = User::factory()->create(['email' => 'invitee@example.com', 'email_verified_at' => now()]);
    $inv = inviteFor($this->workspace->id, $this->owner->id, 'invitee@example.com', ['role' => 'admin']);

    $this->actingAs($u)->post("/invitations/{$inv->id}/accept")->assertRedirect();

    expect(WorkspaceMember::where('workspace_id', $this->workspace->id)->where('user_id', $u->id)->where('role', 'admin')->exists())->toBeTrue()
        ->and($inv->fresh()->accepted_at)->not->toBeNull();
});

it('forbids accepting an invitation addressed to someone else', function () {
    $u = User::factory()->create(['email' => 'other@example.com', 'email_verified_at' => now()]);
    $inv = inviteFor($this->workspace->id, $this->owner->id, 'invitee@example.com');

    $this->actingAs($u)->post("/invitations/{$inv->id}/accept")->assertForbidden();
    expect(WorkspaceMember::where('workspace_id', $this->workspace->id)->where('user_id', $u->id)->exists())->toBeFalse();
});

it('declines an invitation addressed to me: declined_at, no membership', function () {
    $u = User::factory()->create(['email' => 'invitee@example.com', 'email_verified_at' => now()]);
    $inv = inviteFor($this->workspace->id, $this->owner->id, 'invitee@example.com');

    $this->actingAs($u)->post("/invitations/{$inv->id}/decline")->assertRedirect();

    expect($inv->fresh()->declined_at)->not->toBeNull()
        ->and(WorkspaceMember::where('workspace_id', $this->workspace->id)->where('user_id', $u->id)->exists())->toBeFalse();
});
