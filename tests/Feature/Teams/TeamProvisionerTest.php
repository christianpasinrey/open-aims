<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Teams\Support\TeamProvisioner;
use App\Modules\Workspaces\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);
});

it('creates a team and seeds the six canonical workflow states', function () {
    $team = app(TeamProvisioner::class)->create($this->workspace, 'Engineering');

    expect($team->workspace_id)->toBe($this->workspace->id)
        ->and($team->key)->toBe('ENG')
        ->and($team->issue_counter)->toBe(0);

    $states = WorkflowState::where('team_id', $team->id)->orderBy('position')->get();
    expect($states->pluck('name')->all())->toBe(['Triage', 'Backlog', 'Todo', 'In Progress', 'Done', 'Canceled'])
        ->and($states->pluck('type')->all())->toBe(['triage', 'backlog', 'unstarted', 'started', 'completed', 'canceled']);
});

it('auto-generates initials for a multi-word name', function () {
    $team = app(TeamProvisioner::class)->create($this->workspace, 'Patient Care Platform');
    expect($team->key)->toBe('PCP');
});

it('disambiguates a colliding auto-generated key with a numeric suffix', function () {
    app(TeamProvisioner::class)->create($this->workspace, 'Engineering');
    $second = app(TeamProvisioner::class)->create($this->workspace, 'Engineering');
    expect($second->key)->toBe('ENG2');
});

it('normalizes an explicit key (uppercase, alphanumeric, max 8)', function () {
    $team = app(TeamProvisioner::class)->create($this->workspace, 'Whatever', 'my-key!');
    expect($team->key)->toBe('MYKEY');
});

it('falls back to TEAM for a symbols-only name', function () {
    $team = app(TeamProvisioner::class)->create($this->workspace, '!!!');
    expect($team->key)->toBe('TEAM');
});
