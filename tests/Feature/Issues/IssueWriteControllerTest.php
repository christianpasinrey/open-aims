<?php

declare(strict_types=1);

use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('IssueWriteController::store', function () {
    it('creates an issue with auto-incremented number per team', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('issues.store'), [
                'team_key' => 'ENG',
                'title' => 'first',
            ])->assertRedirect();

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('issues.store'), [
                'team_key' => 'ENG',
                'title' => 'second',
            ])->assertRedirect();

        expect(Issue::where('team_id', $this->team->id)->count())->toBe(2);
        expect(Issue::where('team_id', $this->team->id)->pluck('number')->all())
            ->toEqual([1, 2]);
        $this->team->refresh();
        expect((int) $this->team->issue_counter)->toBe(2);
    });

    it('attaches the creator user id to the new issue', function () {
        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('issues.store'), [
                'team_key' => 'ENG',
                'title' => 'mine',
            ]);

        $issue = Issue::where('title', 'mine')->first();
        expect($issue)->not->toBeNull()
            ->and($issue->creator_user_id)->toBe($this->user->id);
    });

    it('rejects missing title with a validation error', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.index'))
            ->post(route('issues.store'), [
                'team_key' => 'ENG',
            ]);

        $response->assertSessionHasErrors('title');
    });

    it('returns 404 when the team does not exist', function () {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('issues.store'), [
                'team_key' => 'NOPE',
                'title' => 'x',
            ]);

        $response->assertNotFound();
    });
});

describe('IssueWriteController::update', function () {
    it('partially updates the title', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'old',
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.index'))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'title' => 'new',
            ])
            ->assertRedirect();

        expect($issue->fresh()->title)->toBe('new');
    });

    it('updates priority alone without touching other columns', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog'], [
            'title' => 'keep',
            'priority' => 0,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.index'))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'priority' => 2,
            ]);

        $fresh = $issue->fresh();
        expect((int) $fresh->priority->value)->toBe(2)
            ->and($fresh->title)->toBe('keep');
    });

    it('sets started_at when transitioning into a started state', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.index'))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'workflow_state_id' => $this->states['In Progress']->id,
            ]);

        expect($issue->fresh()->started_at)->not->toBeNull();
    });

    it('sets completed_at when transitioning into a completed state', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.index'))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'workflow_state_id' => $this->states['Done']->id,
            ]);

        expect($issue->fresh()->completed_at)->not->toBeNull();
    });

    it('sets canceled_at when transitioning into a canceled state', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.index'))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'workflow_state_id' => $this->states['Canceled']->id,
            ]);

        expect($issue->fresh()->canceled_at)->not->toBeNull();
    });

    it('syncs labels — replacing the existing set', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);
        $a = Label::factory()->create(['team_id' => $this->team->id]);
        $b = Label::factory()->create(['team_id' => $this->team->id]);

        $issue->labels()->attach($a->id);

        $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.index'))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'labels' => [$b->id],
            ]);

        $labels = $issue->fresh()->labels->pluck('id')->all();
        expect($labels)->toEqual([$b->id]);
    });

    it('returns 422 when the workflow state belongs to a different team', function () {
        $otherTeam = Team::factory()->create(['workspace_id' => $this->workspace->id, 'key' => 'OPS']);
        $foreignState = WorkflowState::create([
            'team_id' => $otherTeam->id,
            'name' => 'Foreign',
            'type' => 'unstarted',
            'color' => '#000',
            'position' => 0,
        ]);

        $issue = makeIssue($this->team, $this->workspace, $this->states['Backlog']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->from(route('issues.index'))
            ->patch(route('issues.update', ['identifier' => 'ENG-'.$issue->number]), [
                'workflow_state_id' => $foreignState->id,
            ]);

        $response->assertStatus(422);
    });
});
