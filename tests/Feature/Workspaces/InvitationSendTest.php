<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\Notifications\WorkspaceInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

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

it('builds an invitation mail with the accept link', function () {
    $invitation = WorkspaceInvitation::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'new@example.com',
        'role' => 'member',
        'token' => str_repeat('d', 64),
        'invited_by_user_id' => $this->user->id,
        'expires_at' => now()->addDays(3),
    ]);

    // Use a deterministic, special-char-free name: the Blade template HTML-escapes
    // output, so a faker name with an apostrophe (e.g. O'Connell) wouldn't match raw.
    $mail = (new WorkspaceInvitationNotification($invitation, 'Acme Workspace', $this->user->name))
        ->toMail(new AnonymousNotifiable);

    $rendered = $mail->render();
    expect($rendered)->toContain('/invite/'.str_repeat('d', 64))
        ->and($rendered)->toContain('Acme Workspace');
});

it('lets an owner invite an email and sends the notification', function () {
    Notification::fake();

    $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('workspace.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => 'member',
        ])->assertRedirect();

    $inv = WorkspaceInvitation::where('email', 'invitee@example.com')->first();
    expect($inv)->not->toBeNull()
        ->and($inv->workspace_id)->toBe($this->workspace->id)
        ->and(strlen($inv->token))->toBe(64)
        ->and($inv->expires_at->isFuture())->toBeTrue();

    Notification::assertSentOnDemand(
        WorkspaceInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'invitee@example.com',
    );
});

it('forbids a plain member from inviting', function () {
    $member = User::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('workspace.invitations.store'), [
            'email' => 'x@example.com',
            'role' => 'member',
        ])->assertForbidden();
});

it('regenerates the token when re-inviting the same email', function () {
    Notification::fake();
    $first = WorkspaceInvitation::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'again@example.com',
        'role' => 'member',
        'token' => str_repeat('e', 64),
        'invited_by_user_id' => $this->user->id,
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('workspace.invitations.store'), ['email' => 'again@example.com', 'role' => 'admin']);

    $first->refresh();
    expect($first->token)->not->toBe(str_repeat('e', 64))
        ->and($first->role)->toBe('admin');
    expect(WorkspaceInvitation::where('email', 'again@example.com')->count())->toBe(1);
});

it('clears declined_at when re-inviting a previously declined email', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $inv = \App\Modules\Workspaces\Models\WorkspaceInvitation::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'redo@example.com',
        'role' => 'member',
        'token' => str_repeat('z', 64),
        'invited_by_user_id' => $this->user->id,
        'expires_at' => now()->addDay(),
        'declined_at' => now()->subHour(),
    ]);

    $this->actingAs($this->user)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->post(route('workspace.invitations.store'), ['email' => 'redo@example.com', 'role' => 'member']);

    expect($inv->fresh()->declined_at)->toBeNull();
});
