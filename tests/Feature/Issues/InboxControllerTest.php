<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Issues\Models\Comment;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('InboxController::index', function () {
    it('redirects guests to login', function () {
        $response = $this->get(route('inbox.index'));

        $response->assertRedirect(route('login'));
    });

    it('renders the inbox page for authenticated users', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('inbox.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('inbox/Index'));
    });

    it('produces an "assigned" feed entry for issues assigned to the user', function () {
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'do me',
            'assignee_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('inbox.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('feed', 1)
            ->where('feed.0.kind', 'assigned')
        );
    });

    it('produces a "created" entry for issues the user created where assignee is someone else', function () {
        $other = User::factory()->create();
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'mine but theirs',
            'creator_user_id' => $this->user->id,
            'assignee_user_id' => $other->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('inbox.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('feed.0.kind', 'created')
        );
    });

    it('produces a "commented" entry with snippet for recent comments by another user', function () {
        $other = User::factory()->create();
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'creator_user_id' => $this->user->id,
            'assignee_user_id' => $this->user->id,
        ]);

        Comment::factory()->create([
            'issue_id' => $issue->id,
            'user_id' => $other->id,
            'body' => 'hello there',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('inbox.index'));

        // Feed contains both 'assigned' (because user is assignee) and
        // 'commented'; pluck the kinds and assert 'commented' is present
        // with the correct snippet.
        $feed = $response->viewData('page')['props']['feed'] ?? null;
        expect($feed)->toBeArray();
        $kinds = collect($feed)->pluck('kind')->all();
        expect($kinds)->toContain('commented');
        $commented = collect($feed)->firstWhere('kind', 'commented');
        expect($commented['snippet'] ?? null)->toBe('hello there');
    });
});
