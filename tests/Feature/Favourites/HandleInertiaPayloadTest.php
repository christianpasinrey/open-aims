<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Favourites\Models\UserFavourite;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('HandleInertiaRequests::workspacePayload', function () {
    it('exposes an empty favourites array when the user has no rows', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('inbox.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('workspace.favourites', [])
        );
    });

    it('returns favourites in shared workspace prop, ordered by sort_order then id', function () {
        UserFavourite::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'kind' => 'inbox',
            'label' => 'Inbox',
            'icon' => 'Inbox',
            'href' => '/inbox',
            'sort_order' => 1,
        ]);
        UserFavourite::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'kind' => 'page',
            'label' => 'Projects',
            'icon' => 'FolderKanban',
            'href' => '/projects',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('inbox.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('workspace.favourites', 2)
            // sort_order=0 should come first
            ->where('workspace.favourites.0.label', 'Projects')
            ->where('workspace.favourites.0.kind', 'page')
            ->where('workspace.favourites.0.href', '/projects')
            ->where('workspace.favourites.0.icon', 'FolderKanban')
            ->where('workspace.favourites.1.label', 'Inbox')
        );
    });

    it('only exposes the current user\'s favourites (not other users\')', function () {
        $other = User::factory()->create();
        UserFavourite::create([
            'user_id' => $other->id,
            'workspace_id' => $this->workspace->id,
            'kind' => 'inbox',
            'label' => 'Other inbox',
            'href' => '/inbox',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('inbox.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('workspace.favourites', [])
        );
    });
});
