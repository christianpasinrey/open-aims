<?php

declare(strict_types=1);

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
});

describe('TeamWriteController::update', function () {
    it('updates the name', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('teams.settings', ['key' => 'ENG']))
            ->patch(route('teams.update', ['key' => 'ENG']), [
                'name' => 'Engineering Renamed',
            ])
            ->assertRedirect();

        expect($this->team->fresh()->name)->toBe('Engineering Renamed');
    });

    it('updates the color and prepends # when missing', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('teams.settings', ['key' => 'ENG']))
            ->patch(route('teams.update', ['key' => 'ENG']), [
                'color' => 'ff0000',
            ]);

        expect($this->team->fresh()->color)->toBe('#ff0000');
    });

    it('updates the icon', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('teams.settings', ['key' => 'ENG']))
            ->patch(route('teams.update', ['key' => 'ENG']), [
                'icon' => 'rocket',
            ]);

        expect($this->team->fresh()->icon)->toBe('rocket');
    });

    it('does not allow changing the team key', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('teams.settings', ['key' => 'ENG']))
            ->patch(route('teams.update', ['key' => 'ENG']), [
                'key' => 'NEW',
                'name' => 'still works',
            ]);

        // Key remains 'ENG' — there's no rule allowing key in the request,
        // so the update silently ignores it.
        expect($this->team->fresh()->key)->toBe('ENG');
    });

    it('returns 404 for unknown team', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->patch(route('teams.update', ['key' => 'XXX']), [
                'name' => 'doomed',
            ]);

        $response->assertNotFound();
    });
});
