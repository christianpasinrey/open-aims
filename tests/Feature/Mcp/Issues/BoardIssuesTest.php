<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\IssuesModuleManifest;
use App\Modules\Issues\Mcp\Tools\BoardIssue;
use App\Modules\Issues\Mcp\Tools\BoardIssues;
use App\Modules\Issues\Mcp\Tools\IssuesGet;
use App\Modules\Issues\Mcp\Tools\IssuesList;
use App\Modules\Issues\Models\Comment;
use App\Modules\Projects\Models\Project;

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

const BOARD_ROW_KEYS = [
    'identifier', 'title', 'state', 'priority', 'assignee', 'team',
    'project', 'labels', 'cycle_number', 'updated_at', 'url',
];

/** Call board-issues as the fixture user and return the decoded body. */
function boardIssues(array $fix, array $arguments = []): array
{
    return mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(BoardIssues::class, $arguments)->assertOk()
    );
}

/** Identifiers of a board-issues response, sorted for stable comparison. */
function boardIdentifiers(array $body): array
{
    $ids = array_column($body['issues'], 'identifier');
    sort($ids);

    return $ids;
}

// ---------------------------------------------------------------- board-issues

it('lists issues as board rows', function () {
    $fix = makeWorkspaceFixture();
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'One']);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['In Progress'], ['title' => 'Two']);

    $body = boardIssues($fix);

    expect($body['count'])->toBe(2)
        ->and($body['issues'])->toHaveCount(2)
        ->and(array_keys($body['issues'][0]))->toBe(BOARD_ROW_KEYS)
        ->and($body['issues'][0]['state'])->toHaveKeys(['id', 'name', 'type', 'color']);
});

it('returns only the caller\'s issues with view mine', function () {
    $fix = makeWorkspaceFixture();
    $mine = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'assignee_user_id' => $fix['user']->id,
    ]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    expect(boardIdentifiers(boardIssues($fix, ['view' => 'mine'])))->toBe(['ENG-'.$mine->number]);
});

it('excludes archived issues', function () {
    $fix = makeWorkspaceFixture();
    $live = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['archived_at' => now()]);

    expect(boardIdentifiers(boardIssues($fix)))->toBe(['ENG-'.$live->number]);
});

it('filters by state type', function () {
    $fix = makeWorkspaceFixture();
    $started = makeIssue($fix['team'], $fix['workspace'], $fix['states']['In Progress']);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    expect(boardIdentifiers(boardIssues($fix, ['state' => 'started'])))->toBe(['ENG-'.$started->number]);
});

it('filters by project and reports it on the row', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id, 'name' => 'Apollo']);
    $inProject = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['project_id' => $project->id]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    $body = boardIssues($fix, ['project' => $project->slug]);

    expect(boardIdentifiers($body))->toBe(['ENG-'.$inProject->number])
        ->and($body['issues'][0]['project'])->toBe(['slug' => $project->slug, 'name' => 'Apollo']);
});

it('filters by cycle and reports its number on the row', function () {
    $fix = makeWorkspaceFixture();
    $cycle = Cycle::factory()->create(['team_id' => $fix['team']->id, 'number' => 7]);
    $inCycle = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['cycle_id' => $cycle->id]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    $body = boardIssues($fix, ['cycle_number' => 7]);

    expect(boardIdentifiers($body))->toBe(['ENG-'.$inCycle->number])
        ->and($body['issues'][0]['cycle_number'])->toBe(7);
});

it('matches query against the title case-insensitively', function () {
    $fix = makeWorkspaceFixture();
    $login = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Fix login redirect']);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Update docs']);

    expect(boardIdentifiers(boardIssues($fix, ['query' => 'LOGIN'])))->toBe(['ENG-'.$login->number]);
});

it('matches query against a lower-case identifier', function () {
    $fix = makeWorkspaceFixture();
    $first = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Alpha']);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Beta']);

    expect(boardIdentifiers(boardIssues($fix, ['query' => 'eng-'.$first->number])))
        ->toBe(['ENG-'.$first->number]);
});

it('treats % and _ in query literally', function () {
    $fix = makeWorkspaceFixture();
    $percent = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => '100% done']);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'abc']);

    expect(boardIdentifiers(boardIssues($fix, ['query' => '100%'])))->toBe(['ENG-'.$percent->number])
        ->and(boardIssues($fix, ['query' => 'a_c'])['count'])->toBe(0)
        ->and(boardIssues($fix, ['query' => '%'])['count'])->toBe(1);
});

it('honours limit and rejects one above 200', function () {
    $fix = makeWorkspaceFixture();
    foreach (range(1, 3) as $i) {
        makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    }

    expect(boardIssues($fix, ['limit' => 2])['count'])->toBe(2);

    AimsServer::actingAs($fix['user'])
        ->tool(BoardIssues::class, ['limit' => 201])
        ->assertHasErrors();
});

it('never returns another workspace\'s issues', function () {
    $other = makeWorkspaceFixture(['team' => ['key' => 'OPS']]);
    makeIssue($other['team'], $other['workspace'], $other['states']['Todo'], ['title' => 'Foreign']);

    $fix = makeWorkspaceFixture();
    $own = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Own']);

    expect(boardIdentifiers(boardIssues($fix, ['workspace_slug' => $fix['workspace']->slug])))
        ->toBe(['ENG-'.$own->number]);
});

// ----------------------------------------------------------------- board-issue

it('returns one issue in full', function () {
    $fix = makeWorkspaceFixture();
    $todo = $fix['states']['Todo'];

    $parent = makeIssue($fix['team'], $fix['workspace'], $todo, ['title' => 'Epic']);
    $issue = makeIssue($fix['team'], $fix['workspace'], $todo, [
        'title' => 'Fix login',
        'description' => "Body\n\n## Acceptance criteria\n- Given a, When b, Then c",
        'parent_issue_id' => $parent->id,
    ]);
    $child = makeIssue($fix['team'], $fix['workspace'], $todo, [
        'title' => 'Sub task',
        'parent_issue_id' => $issue->id,
    ]);
    $first = Comment::create(['issue_id' => $issue->id, 'user_id' => $fix['user']->id, 'body' => 'First']);
    $first->forceFill(['created_at' => now()->subMinute()])->save();
    Comment::create(['issue_id' => $issue->id, 'user_id' => $fix['user']->id, 'body' => 'Second']);

    $body = mcpToolJson(
        AimsServer::actingAs($fix['user'])
            ->tool(BoardIssue::class, ['identifier' => 'eng-'.$issue->number])
            ->assertOk()
    );

    expect(array_slice(array_keys($body), 0, count(BOARD_ROW_KEYS)))->toBe(BOARD_ROW_KEYS)
        ->and($body['identifier'])->toBe('ENG-'.$issue->number)
        ->and($body['description'])->toBe("Body\n\n## Acceptance criteria\n- Given a, When b, Then c")
        ->and($body['created_at'])->toBeString()
        ->and(array_column($body['comments'], 'body'))->toBe(['First', 'Second'])
        ->and($body['comments'][0]['author'])->toBe(['id' => $fix['user']->id, 'name' => $fix['user']->name])
        ->and($body['comments'][0])->toHaveKeys(['id', 'author', 'body', 'created_at'])
        ->and($body['parent'])->toBe(['identifier' => 'ENG-'.$parent->number, 'title' => 'Epic'])
        ->and($body['children'])->toHaveCount(1)
        ->and($body['children'][0]['identifier'])->toBe('ENG-'.$child->number)
        ->and($body['children'][0]['title'])->toBe('Sub task')
        ->and($body['children'][0]['state']['name'])->toBe('Todo')
        ->and(array_column($body['states'], 'name'))
        ->toBe(array_keys($fix['states']))
        ->and($body['states'][0])->toHaveKeys(['id', 'name', 'type', 'color', 'position'])
        ->and($body['branch'])->toBe('eng-'.$issue->number.'-fix-login')
        ->and($body['workspace_slug'])->toBe($fix['workspace']->slug);
});

it('returns nulls and empty lists for an issue with nothing optional', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    $body = mcpToolJson(
        AimsServer::actingAs($fix['user'])
            ->tool(BoardIssue::class, ['identifier' => 'ENG-'.$issue->number])
            ->assertOk()
    );

    expect($body['description'])->toBeNull()
        ->and($body['comments'])->toBe([])
        ->and($body['parent'])->toBeNull()
        ->and($body['children'])->toBe([]);
});

it('answers not found for an identifier of another workspace', function () {
    $other = makeWorkspaceFixture(['team' => ['key' => 'OPS']]);
    $foreign = makeIssue($other['team'], $other['workspace'], $other['states']['Todo'], ['title' => 'Secret plan']);

    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])
        ->tool(BoardIssue::class, [
            'identifier' => 'OPS-'.$foreign->number,
            'workspace_slug' => $fix['workspace']->slug,
        ])
        ->assertHasErrors()
        ->assertDontSee('Secret plan');
});

it('rejects a malformed identifier', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])
        ->tool(BoardIssue::class, ['identifier' => 'not an id'])
        ->assertHasErrors();
});

// ---------------------------------------------------------------- registration

it('registers the board tools next to the untouched issue tools', function () {
    $tools = (new IssuesModuleManifest)->mcpTools();

    expect($tools)->toContain(IssuesList::class, IssuesGet::class, BoardIssues::class, BoardIssue::class);
});
