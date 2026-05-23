<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Label;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->fix = makeWorkspaceFixture();
    $this->user = $this->fix['user'];
    $this->workspace = $this->fix['workspace'];
    $this->team = $this->fix['team'];
    $this->states = $this->fix['states'];
});

describe('IssueListController::index', function () {
    it('redirects to onboarding when no workspace context exists', function () {
        // Login a user with no workspace memberships → RequireWorkspace gate redirects.
        app()->forgetInstance('current.workspace');
        $orphan = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($orphan)->get(route('issues.index'));

        $response->assertRedirect(route('onboarding'));
    });

    it('lists every team issue when a team is in scope', function () {
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], ['title' => 'one']);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], ['title' => 'two']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('issues/Index')
            ->has('issues', 2)
        );
    });

    it('filters by assignee=me', function () {
        $other = User::factory()->create();
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'mine',
            'assignee_user_id' => $this->user->id,
        ]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'theirs',
            'assignee_user_id' => $other->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['assignee' => 'me']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'mine')
        );
    });

    it('filters by assignee=unassigned', function () {
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'no-one',
            'assignee_user_id' => null,
        ]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'mine',
            'assignee_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['assignee' => 'unassigned']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'no-one')
        );
    });

    it('filters by numeric assignee id', function () {
        $other = User::factory()->create();
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'assignee_user_id' => $this->user->id,
        ]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'assignee_user_id' => $other->id,
            'title' => 'others',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['assignee' => (string) $other->id]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'others')
        );
    });

    it('filters by state type', function () {
        makeIssue($this->team, $this->workspace, $this->states['Done']);
        makeIssue($this->team, $this->workspace, $this->states['Backlog']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['state' => 'completed']));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('issues', 1));
    });

    it('filters by priority', function () {
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], ['priority' => 1]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], ['priority' => 4]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['priority' => '1']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('issues', 1)
            ->where('issues.0.priority', 1)
        );
    });

    it('filters by project', function () {
        $project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'project_id' => $project->id,
        ]);
        makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'project_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['project' => (string) $project->id]));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('issues', 1));
    });

    it('filters by single label', function () {
        $bug = Label::factory()->create(['team_id' => $this->team->id, 'name' => 'bug']);

        $a = makeIssue($this->team, $this->workspace, $this->states['Backlog']);
        $a->labels()->attach($bug->id);
        makeIssue($this->team, $this->workspace, $this->states['Backlog']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['labels' => (string) $bug->id]));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('issues', 1));
    });

    it('filters by multiple labels (AND semantics)', function () {
        $bug = Label::factory()->create(['team_id' => $this->team->id, 'name' => 'bug']);
        $infra = Label::factory()->create(['team_id' => $this->team->id, 'name' => 'infra']);

        $a = makeIssue($this->team, $this->workspace, $this->states['Backlog']);
        $a->labels()->attach([$bug->id, $infra->id]);

        $b = makeIssue($this->team, $this->workspace, $this->states['Backlog']);
        $b->labels()->attach([$bug->id]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['labels' => $bug->id.','.$infra->id]));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('issues', 1));
    });

    it('passes the requested group filter through the response', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['group' => 'priority']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.group', 'priority')
        );
    });

    it('defaults group to status', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.group', 'status')
            ->where('filters.sort', 'priority')
        );
    });

    it('sorts by updated when sort=updated', function () {
        $first = makeIssue($this->team, $this->workspace, $this->states['Backlog'], ['title' => 'old']);
        $first->updated_at = now()->subDays(2);
        $first->save();

        $second = makeIssue($this->team, $this->workspace, $this->states['Backlog'], ['title' => 'new']);
        $second->updated_at = now();
        $second->save();

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('issues.index', ['sort' => 'updated']));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('issues.0.title', 'new')
            ->where('issues.1.title', 'old')
        );
    });
});
