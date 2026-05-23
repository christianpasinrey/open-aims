<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;

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

it('lets any authenticated user create a workspace and become its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('workspaces.store'), ['name' => 'My Space'])
        ->assertRedirect();

    $ws = Workspace::where('name', 'My Space')->first();
    expect($ws)->not->toBeNull()
        ->and($ws->owner_user_id)->toBe($user->id)
        ->and($ws->join_policy)->toBe('request')
        ->and($ws->slug)->toStartWith('my-space-');

    expect(WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $user->id)->where('role', 'owner')->exists())->toBeTrue();
});

it('sets the new workspace as the active one in session', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->post(route('workspaces.store'), ['name' => 'Switch Space']);
    $ws = Workspace::where('name', 'Switch Space')->first();
    $res->assertSessionHas('current_workspace_id', $ws->id);
});

it('accepts an explicit join_policy', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('workspaces.store'), ['name' => 'Open Space', 'join_policy' => 'open']);
    expect(Workspace::where('name', 'Open Space')->first()->join_policy)->toBe('open');
});

it('requires a name', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->from('/onboarding')
        ->post(route('workspaces.store'), [])
        ->assertSessionHasErrors('name');
});

it('rejects an unknown join_policy', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->from('/onboarding')
        ->post(route('workspaces.store'), ['name' => 'X', 'join_policy' => 'superadmin'])
        ->assertSessionHasErrors('join_policy');
});
