<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceJoinRequest;

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
