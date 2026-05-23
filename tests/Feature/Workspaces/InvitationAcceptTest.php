<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceInvitation;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
});

function makeInvite(int $workspaceId, int $inviterId, array $overrides = []): WorkspaceInvitation
{
    return WorkspaceInvitation::create(array_merge([
        'workspace_id' => $workspaceId,
        'email' => 'invitee@example.com',
        'role' => 'member',
        'token' => str_repeat('a', 64),
        'invited_by_user_id' => $inviterId,
        'expires_at' => now()->addDays(3),
    ], $overrides));
}

it('shows the accept page for a valid token', function () {
    makeInvite($this->workspace->id, $this->owner->id);
    $this->get('/invite/'.str_repeat('a', 64))->assertOk();
});

it('shows an invalid state for an expired token', function () {
    makeInvite($this->workspace->id, $this->owner->id, [
        'token' => str_repeat('b', 64),
        'expires_at' => now()->subDay(),
    ]);
    $this->get('/invite/'.str_repeat('b', 64))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/AcceptInvitation')->where('valid', false));
});
