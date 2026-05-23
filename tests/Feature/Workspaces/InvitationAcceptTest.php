<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;

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
    $this->get('/invite/'.str_repeat('a', 64))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/AcceptInvitation')
            ->where('valid', true)
            ->where('email', 'invitee@example.com')
            ->where('workspaceName', $this->workspace->name));
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

it('shows an invalid state for an already-accepted token', function () {
    makeInvite($this->workspace->id, $this->owner->id, [
        'token' => str_repeat('h', 64),
        'accepted_at' => now(),
    ]);
    $this->get('/invite/'.str_repeat('h', 64))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/AcceptInvitation')->where('valid', false));
});

it('creates the account and membership for a new email', function () {
    makeInvite($this->workspace->id, $this->owner->id, ['token' => str_repeat('c', 64)]);

    $this->post('/invite/'.str_repeat('c', 64), [
        'name' => 'New Person',
        'password' => 'Sup3r-secret-pw!',
        'password_confirmation' => 'Sup3r-secret-pw!',
    ])->assertRedirect(route('issues.index'));

    $user = User::where('email', 'invitee@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Auth::id())->toBe($user->id);
    expect(WorkspaceMember::where('workspace_id', $this->workspace->id)->where('user_id', $user->id)->where('role', 'member')->exists())->toBeTrue();
    expect(WorkspaceInvitation::where('token', str_repeat('c', 64))->first()->accepted_at)->not->toBeNull();
});

it('rejects a reused (already accepted) token', function () {
    makeInvite($this->workspace->id, $this->owner->id, [
        'token' => str_repeat('d', 64),
        'accepted_at' => now(),
    ]);

    $this->post('/invite/'.str_repeat('d', 64), [
        'name' => 'X',
        'password' => 'Sup3r-secret-pw!',
        'password_confirmation' => 'Sup3r-secret-pw!',
    ])->assertRedirect(route('login'));

    expect(User::where('email', 'invitee@example.com')->exists())->toBeFalse();
});

it('adds membership for an existing logged-in account without creating a user', function () {
    $existing = User::factory()->create(['email' => 'invitee@example.com']);
    makeInvite($this->workspace->id, $this->owner->id, ['token' => str_repeat('f', 64)]);

    $before = User::count();

    $this->actingAs($existing)
        ->post('/invite/'.str_repeat('f', 64))
        ->assertRedirect(route('issues.index'));

    expect(User::count())->toBe($before)
        ->and(WorkspaceMember::where('workspace_id', $this->workspace->id)->where('user_id', $existing->id)->exists())->toBeTrue();
});

it('asks an existing account to log in when not authenticated as that user', function () {
    User::factory()->create(['email' => 'invitee@example.com']);
    makeInvite($this->workspace->id, $this->owner->id, ['token' => str_repeat('g', 64)]);

    $this->post('/invite/'.str_repeat('g', 64))
        ->assertRedirect(route('login'));
});
