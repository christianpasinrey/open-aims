<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Notifications\IssueAssignedNotification;
use App\Modules\Issues\Notifications\IssueStatusChangedNotification;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Notifications\NewProjectNotification;
use App\Modules\Projects\Notifications\ProjectStatusChangedNotification;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];          // owner + member
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];

    // Two extra workspace members.
    $this->memberA = User::factory()->create();
    $this->memberB = User::factory()->create();
    foreach ([$this->memberA, $this->memberB] as $u) {
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $u->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }
});

describe('new project', function () {
    it('notifies every workspace member except the creator', function () {
        Notification::fake();

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('projects.store'), ['name' => 'Launch', 'team_keys' => ['ENG']])
            ->assertRedirect();

        Notification::assertSentTo([$this->memberA, $this->memberB], NewProjectNotification::class);
        Notification::assertNotSentTo($this->user, NewProjectNotification::class);
    });
});

describe('issue assigned', function () {
    it('notifies the assignee on creation', function () {
        Notification::fake();

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('issues.store'), [
                'title' => 'Bug',
                'team_key' => 'ENG',
                'assignee_user_id' => $this->memberA->id,
            ])->assertRedirect();

        Notification::assertSentTo($this->memberA, IssueAssignedNotification::class);
    });

    it('does not notify when you assign the issue to yourself', function () {
        Notification::fake();

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('issues.store'), [
                'title' => 'Mine',
                'team_key' => 'ENG',
                'assignee_user_id' => $this->user->id,
            ])->assertRedirect();

        Notification::assertNotSentTo($this->user, IssueAssignedNotification::class);
    });

    it('notifies the new assignee on update', function () {
        Notification::fake();

        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.show', ['identifier' => 'ENG-'.$issue->number]))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'assignee_user_id' => $this->memberB->id,
            ]);

        Notification::assertSentTo($this->memberB, IssueAssignedNotification::class);
    });
});

describe('issue status change', function () {
    it('notifies the creator when someone else changes the status', function () {
        Notification::fake();

        // Issue created by the owner.
        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo'], [
            'creator_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->memberA)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.show', ['identifier' => 'ENG-'.$issue->number]))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'workflow_state_id' => $this->states['In Progress']->id,
            ]);

        Notification::assertSentTo($this->user, IssueStatusChangedNotification::class);
    });

    it('does not notify the creator when the creator changes the status', function () {
        Notification::fake();

        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo'], [
            'creator_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.show', ['identifier' => 'ENG-'.$issue->number]))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'workflow_state_id' => $this->states['In Progress']->id,
            ]);

        Notification::assertNotSentTo($this->user, IssueStatusChangedNotification::class);
    });
});

describe('project status change', function () {
    it('notifies the creator when the state changes', function () {
        Notification::fake();

        $project = Project::factory()->create([
            'workspace_id' => $this->workspace->id,
            'state' => 'started',
            'creator_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->memberA)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('projects.show', ['slug' => $project->slug]))
            ->patch(route('projects.update', ['slug' => $project->slug]), ['state' => 'completed']);

        Notification::assertSentTo($this->user, ProjectStatusChangedNotification::class);
    });
});

describe('mcp / direct-save path', function () {
    it('fires the assignee notification even without going through the controller', function () {
        Notification::fake();

        // Simulate an MCP tool: authenticated user saves the model directly.
        $this->actingAs($this->user);

        makeIssue($this->team, $this->workspace, $this->states['Todo'], [
            'assignee_user_id' => $this->memberA->id,
        ]);

        Notification::assertSentTo($this->memberA, IssueAssignedNotification::class);
    });
});
