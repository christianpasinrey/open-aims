<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceMember;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
});

it('lets an owner enable telegram with a chat id', function () {
    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->from('/workspace/settings')
        ->patch(route('workspace.update', ['slug' => $this->workspace->slug]), [
            'telegram_enabled' => true,
            'telegram_chat_id' => '-100ABC',
        ])->assertRedirect();

    $tg = $this->workspace->fresh()->settings['telegram'] ?? [];
    expect($tg['enabled'] ?? null)->toBeTrue()->and($tg['chat_id'] ?? null)->toBe('-100ABC');
});

it('clears the chat id when sent empty', function () {
    $this->workspace->update(['settings' => ['telegram' => ['enabled' => true, 'chat_id' => '-100OLD']]]);

    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->patch(route('workspace.update', ['slug' => $this->workspace->slug]), [
            'telegram_enabled' => true,
            'telegram_chat_id' => '',
        ]);

    $tg = $this->workspace->fresh()->settings['telegram'] ?? [];
    expect($tg['enabled'] ?? null)->toBeTrue()->and($tg['chat_id'] ?? null)->toBeNull();
});

it('forbids a plain member from changing telegram settings', function () {
    $member = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($member)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->patch(route('workspace.update', ['slug' => $this->workspace->slug]), ['telegram_enabled' => true])
        ->assertForbidden();
});
