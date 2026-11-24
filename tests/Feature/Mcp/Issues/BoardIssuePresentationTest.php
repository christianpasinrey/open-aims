<?php

declare(strict_types=1);

use App\Modules\Issues\Mcp\Tools\PresentsBoardIssues;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\WorkflowState;

/** The trait under test, with its protected methods opened up. */
function boardPresenter(): object
{
    return new class
    {
        use PresentsBoardIssues {
            boardIssueRelations as public;
            boardIssueRow as public;
            boardStateRow as public;
            issueBranchName as public;
        }
    };
}

it('maps an issue to the board row', function () {
    config(['app.url' => 'https://aims.example/']);
    $fix = makeWorkspaceFixture();
    $todo = $fix['states']['Todo'];
    $label = Label::create(['team_id' => $fix['team']->id, 'name' => 'bug', 'color' => '#ef4444']);

    $issue = makeIssue($fix['team'], $fix['workspace'], $todo, [
        'title' => 'Fix login',
        'priority' => 2,
        'assignee_user_id' => $fix['user']->id,
    ]);
    $issue->labels()->attach($label->id);

    $presenter = boardPresenter();
    $row = $presenter->boardIssueRow(
        Issue::query()->with($presenter->boardIssueRelations())->findOrFail($issue->id)
    );

    expect($row)->toBe([
        'identifier' => 'ENG-'.$issue->number,
        'title' => 'Fix login',
        'state' => ['id' => $todo->id, 'name' => 'Todo', 'type' => 'unstarted', 'color' => '#475569'],
        'priority' => ['value' => 2, 'label' => 'High'],
        'assignee' => ['id' => $fix['user']->id, 'name' => $fix['user']->name],
        'team' => ['key' => 'ENG', 'color' => $fix['team']->color],
        'project' => null,
        'labels' => [['name' => 'bug', 'color' => '#ef4444']],
        'cycle_number' => null,
        'updated_at' => $issue->fresh()->updated_at->toIso8601String(),
        'url' => 'https://aims.example/issues/ENG-'.$issue->number,
    ]);
});

it('returns nulls and empty arrays for an issue with nothing optional set', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Backlog']);

    $presenter = boardPresenter();
    $row = $presenter->boardIssueRow(
        Issue::query()->with($presenter->boardIssueRelations())->findOrFail($issue->id)
    );

    expect($row['assignee'])->toBeNull()
        ->and($row['project'])->toBeNull()
        ->and($row['cycle_number'])->toBeNull()
        ->and($row['labels'])->toBe([])
        ->and($row['priority'])->toBe(['value' => 0, 'label' => 'No priority']);
});

it('maps a workflow state', function () {
    $fix = makeWorkspaceFixture();
    /** @var WorkflowState $state */
    $state = $fix['states']['In Progress'];

    expect(boardPresenter()->boardStateRow($state))->toBe([
        'id' => $state->id,
        'name' => 'In Progress',
        'type' => 'started',
        'color' => '#facc15',
        'position' => 3,
    ]);
});

it('builds the branch name from the identifier and the title', function (string $title, string $expected) {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'title' => $title,
        'number' => 12,
    ]);

    expect(boardPresenter()->issueBranchName($issue->load('team')))->toBe($expected);
})->with([
    'plain' => ['Fix login redirect', 'eng-12-fix-login-redirect'],
    'accents and punctuation' => ['Añadir tokens — ¡ya!', 'eng-12-anadir-tokens-ya'],
    'nothing sluggable' => ['🚀🚀', 'eng-12'],
]);

it('cuts a long branch name to 60 characters without a trailing hyphen', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        // "eng-12-" + 53 characters lands exactly on a hyphen.
        'title' => str_repeat('abcdefgh ', 12),
        'number' => 12,
    ]);

    $branch = boardPresenter()->issueBranchName($issue->load('team'));

    expect(strlen($branch))->toBeLessThanOrEqual(60)
        ->and($branch)->toStartWith('eng-12-abcdefgh-')
        ->and($branch)->not->toEndWith('-')
        ->and($branch)->toBe('eng-12-abcdefgh-abcdefgh-abcdefgh-abcdefgh-abcdefgh-abcdefgh');
});

it('prefers the stored branch name', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'title' => 'Fix login',
        'git_branch_name' => 'feat/custom',
    ]);

    expect(boardPresenter()->issueBranchName($issue->load('team')))->toBe('feat/custom');
});
