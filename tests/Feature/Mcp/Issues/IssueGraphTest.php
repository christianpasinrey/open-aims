<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Issues\Mcp\Tools\IssuesGet;
use App\Modules\Issues\Models\IssueRelation;

if (! function_exists('mcpToolJson')) {
    /**
     * Decode the JSON body a tool returned via Response::json(). The package's
     * TestResponse only exposes it through a protected accessor.
     *
     * @return array<string, mixed>
     */
    function mcpToolJson(object $response): array
    {
        $method = new ReflectionMethod($response, 'content');
        $method->setAccessible(true);

        /** @var array<int, string> $texts */
        $texts = $method->invoke($response);

        return json_decode(implode('', $texts), true) ?: [];
    }
}

it('reports each relation direction separately in issues-get', function () {
    $fix = makeWorkspaceFixture();
    $todo = $fix['states']['Todo'];

    $a = makeIssue($fix['team'], $fix['workspace'], $todo, ['title' => 'A']);
    $b = makeIssue($fix['team'], $fix['workspace'], $todo, ['title' => 'B']);
    $c = makeIssue($fix['team'], $fix['workspace'], $todo, ['title' => 'C']);
    $d = makeIssue($fix['team'], $fix['workspace'], $todo, ['title' => 'D']);

    // A blocks B; C is related to A (edge stored C -> A); A duplicates D.
    IssueRelation::create(['source_issue_id' => $a->id, 'target_issue_id' => $b->id, 'type' => 'blocks']);
    IssueRelation::create(['source_issue_id' => $c->id, 'target_issue_id' => $a->id, 'type' => 'related']);
    IssueRelation::create(['source_issue_id' => $a->id, 'target_issue_id' => $d->id, 'type' => 'duplicate']);

    $fromA = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesGet::class, ['identifier' => 'ENG-'.$a->number])->assertOk()
    );

    expect(array_column($fromA['blocks'], 'identifier'))->toBe(['ENG-'.$b->number])
        ->and($fromA['blocked_by'])->toBe([])
        ->and(array_column($fromA['related'], 'identifier'))->toBe(['ENG-'.$c->number])
        ->and(array_column($fromA['duplicate_of'], 'identifier'))->toBe(['ENG-'.$d->number])
        ->and($fromA['blocks'][0]['title'])->toBe('B')
        ->and($fromA['blocks'][0]['state'])->toBe('Todo');

    // The very same 'blocks' row must read as blocked_by from the target side.
    $fromB = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesGet::class, ['identifier' => 'ENG-'.$b->number])->assertOk()
    );

    expect(array_column($fromB['blocked_by'], 'identifier'))->toBe(['ENG-'.$a->number])
        ->and($fromB['blocks'])->toBe([]);

    // 'related' is symmetric: C sees A even though the row points C -> A.
    $fromC = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesGet::class, ['identifier' => 'ENG-'.$c->number])->assertOk()
    );

    expect(array_column($fromC['related'], 'identifier'))->toBe(['ENG-'.$a->number]);

    // D is duplicated BY A, so its own duplicate_of stays empty.
    $fromD = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesGet::class, ['identifier' => 'ENG-'.$d->number])->assertOk()
    );

    expect($fromD['duplicate_of'])->toBe([]);
});

it('returns empty relation lists for an unlinked issue', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesGet::class, ['identifier' => 'ENG-'.$issue->number])->assertOk()
    );

    expect($json)->toHaveKeys(['blocks', 'blocked_by', 'related', 'duplicate_of'])
        ->and($json['blocks'])->toBe([])
        ->and($json['blocked_by'])->toBe([])
        ->and($json['related'])->toBe([])
        ->and($json['duplicate_of'])->toBe([]);
});
