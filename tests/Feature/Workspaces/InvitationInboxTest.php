<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceInvitation;

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
