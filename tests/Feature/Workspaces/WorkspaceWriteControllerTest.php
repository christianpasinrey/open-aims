<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
});

describe('WorkspaceWriteController::switch', function () {
    it('changes the session active workspace id', function () {
        $other = Workspace::factory()->create(['owner_user_id' => $this->user->id]);
        WorkspaceMember::create([
            'workspace_id' => $other->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('workspace.switch', ['workspace' => $other->slug]));

        $response->assertRedirect();
        // Re-fire any GET — the session should now hold the new workspace id.
        expect(session('current_workspace_id'))->toBe($other->id);
    });

    it('returns 404 for an unknown workspace slug', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('workspace.switch', ['workspace' => 'unknown-slug']));

        $response->assertNotFound();
    });

    it('forbids switching into a workspace you are not a member of', function () {
        $foreign = Workspace::factory()->create();

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('workspace.switch', ['workspace' => $foreign->slug]));

        $response->assertForbidden();
    });
});

describe('WorkspaceWriteController::update', function () {
    it('renames the workspace', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('workspace.settings'))
            ->patch(route('workspace.update', ['slug' => $this->workspace->slug]), [
                'name' => 'Renamed',
            ])
            ->assertRedirect();

        expect($this->workspace->fresh()->name)->toBe('Renamed');
    });

    it('persists the color into settings JSON', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('workspace.settings'))
            ->patch(route('workspace.update', ['slug' => $this->workspace->slug]), [
                'color' => '#abcdef',
            ])
            ->assertRedirect();

        $settings = $this->workspace->fresh()->settings ?? [];
        expect($settings['color'] ?? null)->toBe('#abcdef');
    });

    it('forbids non-admin members from updating', function () {
        $member = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($member)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->patch(route('workspace.update', ['slug' => $this->workspace->slug]), [
                'name' => 'Hijack',
            ]);

        $response->assertForbidden();
    });
});
