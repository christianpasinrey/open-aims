<?php

declare(strict_types=1);

use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;

describe('App\\Core\\Scopes\\WorkspaceScope', function () {
    it('is a no-op when no workspace is bound', function () {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();

        $teamA = Team::factory()->create(['workspace_id' => $a->id]);
        $teamB = Team::factory()->create(['workspace_id' => $b->id]);
        $stateA = WorkflowState::factory()->create(['team_id' => $teamA->id]);
        $stateB = WorkflowState::factory()->create(['team_id' => $teamB->id]);

        Issue::factory()->create([
            'workspace_id' => $a->id,
            'team_id' => $teamA->id,
            'workflow_state_id' => $stateA->id,
        ]);
        Issue::factory()->create([
            'workspace_id' => $b->id,
            'team_id' => $teamB->id,
            'workflow_state_id' => $stateB->id,
        ]);

        app()->forgetInstance('current.workspace');

        // No workspace bound → both rows visible.
        expect(Issue::query()->count())->toBe(2);
    });

    it('constrains queries to the bound workspace', function () {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();

        $teamA = Team::factory()->create(['workspace_id' => $a->id]);
        $teamB = Team::factory()->create(['workspace_id' => $b->id]);
        $stateA = WorkflowState::factory()->create(['team_id' => $teamA->id]);
        $stateB = WorkflowState::factory()->create(['team_id' => $teamB->id]);

        Issue::factory()->create([
            'workspace_id' => $a->id,
            'team_id' => $teamA->id,
            'workflow_state_id' => $stateA->id,
        ]);
        Issue::factory()->create([
            'workspace_id' => $b->id,
            'team_id' => $teamB->id,
            'workflow_state_id' => $stateB->id,
        ]);

        app()->instance('current.workspace', $a);

        expect(Issue::query()->count())->toBe(1)
            ->and(Issue::query()->first()->workspace_id)->toBe($a->id);
    });

    it('can be bypassed with withoutGlobalScopes()', function () {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();

        $teamA = Team::factory()->create(['workspace_id' => $a->id]);
        $teamB = Team::factory()->create(['workspace_id' => $b->id]);
        $stateA = WorkflowState::factory()->create(['team_id' => $teamA->id]);
        $stateB = WorkflowState::factory()->create(['team_id' => $teamB->id]);

        Issue::factory()->create([
            'workspace_id' => $a->id,
            'team_id' => $teamA->id,
            'workflow_state_id' => $stateA->id,
        ]);
        Issue::factory()->create([
            'workspace_id' => $b->id,
            'team_id' => $teamB->id,
            'workflow_state_id' => $stateB->id,
        ]);

        app()->instance('current.workspace', $a);

        expect(Issue::query()->withoutGlobalScopes()->count())->toBe(2);
    });
});

describe('App\\Core\\Concerns\\BelongsToWorkspace', function () {
    it('auto-fills workspace_id on creating when bound', function () {
        $ws = Workspace::factory()->create();
        $team = Team::factory()->create(['workspace_id' => $ws->id]);
        $state = WorkflowState::factory()->create(['team_id' => $team->id]);

        app()->instance('current.workspace', $ws);

        $issue = Issue::create([
            'team_id' => $team->id,
            'number' => 99,
            'title' => 'auto',
            'workflow_state_id' => $state->id,
            'priority' => 0,
            'creator_user_id' => $ws->owner_user_id,
        ]);

        expect((int) $issue->workspace_id)->toBe($ws->id);
    });

    it('does not override an explicitly provided workspace_id', function () {
        $ws = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $team = Team::factory()->create(['workspace_id' => $other->id]);
        $state = WorkflowState::factory()->create(['team_id' => $team->id]);

        app()->instance('current.workspace', $ws);

        $issue = Issue::query()->withoutGlobalScopes()->create([
            'workspace_id' => $other->id,
            'team_id' => $team->id,
            'number' => 99,
            'title' => 'explicit',
            'workflow_state_id' => $state->id,
            'priority' => 0,
            'creator_user_id' => $other->owner_user_id,
        ]);

        expect((int) $issue->workspace_id)->toBe($other->id);
    });
});
