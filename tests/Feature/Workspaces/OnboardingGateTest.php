<?php

declare(strict_types=1);

use App\Models\User;

it('redirects a verified user with no workspace to onboarding', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->get('/issues')->assertRedirect('/onboarding');
});

it('does not redirect on the onboarding page itself', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->get('/onboarding')->assertOk();
});

it('does not redirect a user who has a workspace', function () {
    $fix = makeWorkspaceFixture();
    $this->actingAs($fix['user'])
        ->withSession(['current_workspace_id' => $fix['workspace']->id])
        ->get('/issues')
        ->assertOk();
});

it('does not redirect the create-workspace POST', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->post(route('workspaces.store'), ['name' => 'Bootstrap'])
        ->assertRedirect(route('issues.index'));
});
