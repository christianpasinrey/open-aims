<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Favourites\Models\UserFavourite;
use App\Modules\Issues\Models\Issue;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('FavouriteController::toggle', function () {
    it('redirects guests to login', function () {
        $response = $this->post(route('favourites.toggle'), [
            'kind' => 'inbox',
            'href' => '/inbox',
            'label' => 'Inbox',
        ]);

        $response->assertRedirect(route('login'));
    });

    it('creates a row when starring a page (inbox)', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'inbox',
                'href' => '/inbox',
                'label' => 'Inbox',
                'icon' => 'Inbox',
            ]);

        $response->assertRedirect();

        $row = UserFavourite::query()
            ->where('user_id', $this->user->id)
            ->where('workspace_id', $this->workspace->id)
            ->where('kind', 'inbox')
            ->first();

        expect($row)->not->toBeNull();
        expect($row->href)->toBe('/inbox');
        expect($row->label)->toBe('Inbox');
        expect($row->icon)->toBe('Inbox');
    });

    it('toggles off when called twice (idempotent)', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'inbox',
                'href' => '/inbox',
                'label' => 'Inbox',
            ]);

        expect(UserFavourite::query()->where('user_id', $this->user->id)->count())->toBe(1);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'inbox',
                'href' => '/inbox',
                'label' => 'Inbox',
            ]);

        expect(UserFavourite::query()->where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('starring an issue stores target_type and target_id', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'issue',
                'href' => '/issues/ENG-'.$issue->number,
                'label' => 'ENG-'.$issue->number.' Test',
                'icon' => 'Circle',
                'target_type' => Issue::class,
                'target_id' => $issue->id,
            ]);

        $row = UserFavourite::query()
            ->where('user_id', $this->user->id)
            ->where('kind', 'issue')
            ->first();

        expect($row)->not->toBeNull();
        expect($row->target_type)->toBe(Issue::class);
        expect((int) $row->target_id)->toBe($issue->id);
    });

    it('is scoped per user — User A starring does not show for User B', function () {
        $userB = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $userB->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'inbox',
                'href' => '/inbox',
                'label' => 'Inbox',
            ]);

        $countB = UserFavourite::query()
            ->where('user_id', $userB->id)
            ->where('workspace_id', $this->workspace->id)
            ->count();

        expect($countB)->toBe(0);
    });

    it('is scoped per workspace — A favourite in workspace1 is not visible in workspace2', function () {
        $userId = $this->user->id;

        $workspace2 = Workspace::factory()->create(['owner_user_id' => $userId]);
        WorkspaceMember::create([
            'workspace_id' => $workspace2->id,
            'user_id' => $userId,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'inbox',
                'href' => '/inbox',
                'label' => 'Inbox',
            ]);

        $count = UserFavourite::query()
            ->where('user_id', $userId)
            ->where('workspace_id', $workspace2->id)
            ->count();

        expect($count)->toBe(0);
    });

    it('rejects unknown kind', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'random_kind',
                'href' => '/x',
                'label' => 'X',
            ]);

        $response->assertSessionHasErrors('kind');
    });
});

describe('FavouriteController::destroy', function () {
    it('removes the favourite row', function () {
        $fav = UserFavourite::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'kind' => 'inbox',
            'target_type' => null,
            'target_id' => null,
            'label' => 'Inbox',
            'icon' => 'Inbox',
            'color' => null,
            'href' => '/inbox',
            'sort_order' => 0,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->delete(route('favourites.destroy', ['id' => $fav->id]));

        expect(UserFavourite::query()->where('id', $fav->id)->exists())->toBeFalse();
    });

    it('returns 404 for another user\'s favourite', function () {
        $userB = User::factory()->create();
        $fav = UserFavourite::create([
            'user_id' => $userB->id,
            'workspace_id' => $this->workspace->id,
            'kind' => 'inbox',
            'label' => 'Inbox',
            'icon' => 'Inbox',
            'href' => '/inbox',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->delete(route('favourites.destroy', ['id' => $fav->id]));

        $response->assertNotFound();

        // Row was not deleted
        expect(UserFavourite::query()->where('id', $fav->id)->exists())->toBeTrue();
    });
});
