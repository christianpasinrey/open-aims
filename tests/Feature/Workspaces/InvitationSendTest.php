<?php

declare(strict_types=1);

use App\Modules\Workspaces\Models\WorkspaceInvitation;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
});

it('reports an invitation as acceptable only when pending and unexpired', function () {
    $valid = WorkspaceInvitation::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'a@example.com',
        'role' => 'member',
        'token' => str_repeat('a', 64),
        'invited_by_user_id' => $this->user->id,
        'expires_at' => now()->addDay(),
    ]);
    $expired = WorkspaceInvitation::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'b@example.com',
        'role' => 'member',
        'token' => str_repeat('b', 64),
        'invited_by_user_id' => $this->user->id,
        'expires_at' => now()->subDay(),
    ]);
    $accepted = WorkspaceInvitation::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'c@example.com',
        'role' => 'member',
        'token' => str_repeat('c', 64),
        'invited_by_user_id' => $this->user->id,
        'expires_at' => now()->addDay(),
        'accepted_at' => now(),
    ]);

    expect($valid->isAcceptable())->toBeTrue()
        ->and($expired->isAcceptable())->toBeFalse()
        ->and($accepted->isAcceptable())->toBeFalse();
});
