<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];

    // Add another member so the list has > 1 row.
    $other = User::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $other->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);
});

describe('WorkspaceMembersPageController::index', function () {
    it('returns the Inertia page on default Accept', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('workspace.members'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('workspace/Members')
            ->has('members', 2)
        );
    });

    it('returns JSON when ?json=1 is set', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('workspace.members', ['json' => '1']));

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'email']]]);
        expect($response->json('data'))->toHaveCount(2);
    });

    it('returns JSON when Accept: application/json is sent', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->getJson(route('workspace.members'));

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'email']]]);
    });
});
