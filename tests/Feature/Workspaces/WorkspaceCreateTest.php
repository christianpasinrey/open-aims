<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;

it('persists join_policy on a workspace', function () {
    $user = User::factory()->create();
    $ws = Workspace::create([
        'name' => 'Acme',
        'slug' => 'acme-'.uniqid(),
        'owner_user_id' => $user->id,
        'join_policy' => 'open',
    ]);

    expect($ws->fresh()->join_policy)->toBe('open');
});
