<?php

declare(strict_types=1);

use App\Modules\Favourites\Models\UserFavourite;
use App\Modules\Issues\Models\Issue;
use App\Modules\Views\Models\IssueView;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('Issue detail star → /favourites/toggle → sidebar payload', function () {
    it('starring an issue surfaces it in workspace.favourites on the next request', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'Star me',
        ]);

        $href = '/issues/ENG-'.$issue->number;
        $label = 'ENG-'.$issue->number.' Star me';

        // Simulate clicking the star
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'issue',
                'href' => $href,
                'label' => $label,
                'icon' => 'Circle',
                'target_type' => Issue::class,
                'target_id' => $issue->id,
            ]);

        // Row should exist
        expect(UserFavourite::query()
            ->where('user_id', $this->user->id)
            ->where('kind', 'issue')
            ->where('target_id', $issue->id)
            ->count())->toBe(1);

        // The next page request should expose the favourite in shared props
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.show', ['identifier' => 'ENG-'.$issue->number]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('workspace.favourites', 1)
            ->where('workspace.favourites.0.kind', 'issue')
            ->where('workspace.favourites.0.label', $label)
            ->where('workspace.favourites.0.href', $href)
            ->where('workspace.favourites.0.icon', 'Circle')
            ->where('workspace.favourites.0.target_id', $issue->id)
        );
    });

    it('starring a view also flips IssueView.is_favorite for backwards compat', function () {
        $view = IssueView::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'My in-progress',
            'description' => null,
            'owner_user_id' => $this->user->id,
            'scope' => 'personal',
            'team_id' => null,
            'filters' => [],
            'grouping' => 'status',
            'sorting' => 'priority',
            'is_favorite' => false,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'view',
                'href' => '/views/'.$view->id,
                'label' => $view->name,
                'icon' => 'Eye',
                'target_type' => IssueView::class,
                'target_id' => $view->id,
            ]);

        $view->refresh();
        expect($view->is_favorite)->toBeTrue();

        // Toggle off
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('favourites.toggle'), [
                'kind' => 'view',
                'href' => '/views/'.$view->id,
                'label' => $view->name,
                'icon' => 'Eye',
                'target_type' => IssueView::class,
                'target_id' => $view->id,
            ]);

        $view->refresh();
        expect($view->is_favorite)->toBeFalse();
    });
});
