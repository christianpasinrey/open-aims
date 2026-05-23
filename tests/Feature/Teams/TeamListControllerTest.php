<?php

declare(strict_types=1);

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->owner = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
});

it('returns teams as JSON', function () {
    $res = $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->getJson('/workspace/teams?json=1');

    $res->assertOk();
    $row = collect($res->json('data'))->firstWhere('key', 'ENG');
    expect($row)->not->toBeNull()
        ->and($row['name'])->toBe($this->team->name)
        ->and($row)->toHaveKeys(['key', 'name', 'color', 'issue_count', 'member_count']);
});

it('renders the teams Inertia page', function () {
    $this->actingAs($this->owner)
        ->withSession(['current_workspace_id' => $this->workspace->id])
        ->get('/workspace/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('workspace/Teams'));
});
