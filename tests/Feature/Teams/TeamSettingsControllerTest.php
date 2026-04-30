<?php

declare(strict_types=1);

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
});

describe('TeamSettingsController::index', function () {
    it('returns 200 for a valid team key', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('teams.settings', ['key' => 'ENG']));

        $response->assertOk();
    });

    it('returns 404 for a missing team key', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('teams.settings', ['key' => 'XXX']));

        $response->assertNotFound();
    });
});

describe('TeamMemberListController::index', function () {
    it('returns 200 for a valid team', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('teams.members', ['key' => 'ENG']));

        $response->assertOk();
    });

    it('returns 404 for a missing team', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('teams.members', ['key' => 'XXX']));

        $response->assertNotFound();
    });
});
