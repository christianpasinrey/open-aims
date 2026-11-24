<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Models\User;
use App\Modules\Issues\IssuesModuleManifest;
use App\Modules\Issues\Mcp\Tools\IssuesStart;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Workspaces\Models\WorkspaceMember;

if (! function_exists('mcpToolJson')) {
    /**
     * Decode the JSON body a tool returned via Response::json().
     *
     * @return array<string, mixed>
     */
    function mcpToolJson(object $response): array
    {
        $method = new ReflectionMethod($response, 'content');
        $method->setAccessible(true);

        return json_decode(implode('', $method->invoke($response)), true) ?: [];
    }
}

/** Call issues-start as the fixture user and return the decoded body. */
function startIssue(array $fix, string $identifier): array
{
    return mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesStart::class, ['identifier' => $identifier])->assertOk()
    );
}

beforeEach(function () {
    config(['app.url' => 'https://aims.example']);
});

it('starts an unstarted issue', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Fix login']);
    $activityBefore = IssueActivity::where('issue_id', $issue->id)->count();

    $body = startIssue($fix, 'ENG-'.$issue->number);

    $issue->refresh();
    $inProgress = $fix['states']['In Progress'];

    expect($body['changed'])->toBeTrue()
        ->and($body['identifier'])->toBe('ENG-'.$issue->number)
        ->and($body['state'])->toBe([
            'id' => $inProgress->id, 'name' => 'In Progress', 'type' => 'started', 'color' => '#facc15',
        ])
        ->and($body['assignee'])->toBe(['id' => $fix['user']->id, 'name' => $fix['user']->name])
        ->and($body['url'])->toBe('https://aims.example/issues/ENG-'.$issue->number)
        ->and($issue->workflow_state_id)->toBe($inProgress->id)
        ->and($issue->assignee_user_id)->toBe($fix['user']->id)
        ->and($issue->started_at)->not->toBeNull()
        ->and(IssueActivity::where('issue_id', $issue->id)->count())->toBeGreaterThan($activityBefore);
});

it('moves to the started state with the lowest position', function () {
    $fix = makeWorkspaceFixture();
    $fix['team']->workflowStates()->create([
        'name' => 'In Review', 'type' => 'started', 'color' => '#38bdf8', 'position' => 9,
    ]);
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Backlog']);

    expect(startIssue($fix, 'ENG-'.$issue->number)['state']['name'])->toBe('In Progress');
});

it('stores the branch name when the issue has none', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Fix login']);

    $body = startIssue($fix, 'ENG-'.$issue->number);

    expect($body['branch'])->toBe('eng-'.$issue->number.'-fix-login')
        ->and($issue->refresh()->git_branch_name)->toBe('eng-'.$issue->number.'-fix-login');
});

it('never overwrites a stored branch', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'git_branch_name' => 'feat/custom',
    ]);

    expect(startIssue($fix, 'ENG-'.$issue->number)['branch'])->toBe('feat/custom')
        ->and($issue->refresh()->git_branch_name)->toBe('feat/custom');
});

it('is idempotent on an issue someone else already started', function () {
    $fix = makeWorkspaceFixture();
    $colleague = User::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $fix['workspace']->id, 'user_id' => $colleague->id,
        'role' => 'member', 'joined_at' => now(),
    ]);
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['In Progress'], [
        'assignee_user_id' => $colleague->id,
        'started_at' => now()->subDay(),
    ]);
    $activityBefore = IssueActivity::where('issue_id', $issue->id)->count();

    $first = startIssue($fix, 'ENG-'.$issue->number);
    $second = startIssue($fix, 'ENG-'.$issue->number);

    expect($first['changed'])->toBeFalse()
        ->and($first['assignee'])->toBe(['id' => $colleague->id, 'name' => $colleague->name])
        ->and($first['state']['name'])->toBe('In Progress')
        ->and($second['brief'])->toBe($first['brief'])
        ->and($second['branch'])->toBe($first['branch'])
        ->and($issue->refresh()->assignee_user_id)->toBe($colleague->id)
        ->and(IssueActivity::where('issue_id', $issue->id)->count())->toBe($activityBefore);
});

it('does not reopen a completed issue', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Done'], ['completed_at' => now()]);

    $body = startIssue($fix, 'ENG-'.$issue->number);

    expect($body['changed'])->toBeFalse()
        ->and($body['state']['name'])->toBe('Done')
        ->and($body['assignee'])->toBeNull()
        ->and($issue->refresh()->workflow_state_id)->toBe($fix['states']['Done']->id);
});

it('builds the brief from the title, the description, the branch and the url', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'title' => 'Fix login',
        'description' => "Body\n\n## Acceptance criteria\n- Given a, When b, Then c\n",
    ]);
    $id = 'ENG-'.$issue->number;

    expect(startIssue($fix, $id)['brief'])->toBe(
        "# {$id}: Fix login\n\n"
        ."Body\n\n## Acceptance criteria\n- Given a, When b, Then c\n\n"
        .'Branch: eng-'.$issue->number."-fix-login\n"
        ."Issue: https://aims.example/issues/{$id}"
    );
});

it('omits the description block when there is none', function (?string $description) {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'title' => 'Fix login',
        'description' => $description,
    ]);
    $id = 'ENG-'.$issue->number;

    expect(startIssue($fix, $id)['brief'])->toBe(
        "# {$id}: Fix login\n\n"
        .'Branch: eng-'.$issue->number."-fix-login\n"
        ."Issue: https://aims.example/issues/{$id}"
    );
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ["  \n "],
]);

it('errors when the team has no started state and changes nothing', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $fix['states']['In Progress']->delete();

    AimsServer::actingAs($fix['user'])
        ->tool(IssuesStart::class, ['identifier' => 'ENG-'.$issue->number])
        ->assertHasErrors(["Team 'ENG' has no started state."]);

    $issue->refresh();
    expect($issue->workflow_state_id)->toBe($fix['states']['Todo']->id)
        ->and($issue->assignee_user_id)->toBeNull()
        ->and($issue->git_branch_name)->toBeNull();
});

it('answers not found for another workspace\'s identifier and changes nothing', function () {
    $other = makeWorkspaceFixture(['team' => ['key' => 'OPS']]);
    $foreign = makeIssue($other['team'], $other['workspace'], $other['states']['Todo']);

    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])
        ->tool(IssuesStart::class, [
            'identifier' => 'OPS-'.$foreign->number,
            'workspace_slug' => $fix['workspace']->slug,
        ])
        ->assertHasErrors();

    $foreign->refresh();
    expect($foreign->workflow_state_id)->toBe($other['states']['Todo']->id)
        ->and($foreign->assignee_user_id)->toBeNull();
});

it('is registered', function () {
    expect((new IssuesModuleManifest)->mcpTools())->toContain(IssuesStart::class);
});
