<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Issues\Mcp\Tools\IssuesGet;
use App\Modules\Issues\Mcp\Tools\IssuesLink;
use App\Modules\Issues\Models\IssueRelation;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;

if (! function_exists('mcpToolJson')) {
    /**
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

it('creates a blocks edge in the stated direction', function () {
    $fix = makeWorkspaceFixture();
    $a = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'blocker']);
    $b = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'blocked']);

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
            'from' => 'ENG-'.$a->number,
            'to' => 'ENG-'.$b->number,
            'type' => 'blocks',
        ])->assertOk()
    );

    expect($json['action'])->toBe('created')
        ->and(array_column($json['relations']['blocks'], 'identifier'))->toBe(['ENG-'.$b->number]);

    $row = IssueRelation::query()->firstOrFail();
    expect($row->source_issue_id)->toBe($a->id)
        ->and($row->target_issue_id)->toBe($b->id)
        ->and($row->type)->toBe('blocks')
        ->and($row->created_by_user_id)->toBe($fix['user']->id);

    $fromB = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesGet::class, ['identifier' => 'ENG-'.$b->number])->assertOk()
    );
    expect(array_column($fromB['blocked_by'], 'identifier'))->toBe(['ENG-'.$a->number]);
});

it('removes an existing edge with remove=true', function () {
    $fix = makeWorkspaceFixture();
    $a = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $b = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$a->number, 'to' => 'ENG-'.$b->number, 'type' => 'blocks',
    ])->assertOk();

    expect(IssueRelation::count())->toBe(1);

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
            'from' => 'ENG-'.$a->number,
            'to' => 'ENG-'.$b->number,
            'type' => 'blocks',
            'remove' => true,
        ])->assertOk()
    );

    expect($json['action'])->toBe('removed')
        ->and($json['relations']['blocks'])->toBe([])
        ->and(IssueRelation::count())->toBe(0);
});

it('removes a symmetric related edge from either side', function () {
    $fix = makeWorkspaceFixture();
    $a = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $b = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$a->number, 'to' => 'ENG-'.$b->number, 'type' => 'related',
    ])->assertOk();

    // Removing from the far end must find the same row.
    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$b->number, 'to' => 'ENG-'.$a->number, 'type' => 'related', 'remove' => true,
    ])->assertOk();

    expect(IssueRelation::count())->toBe(0);
});

it('rejects a self-link', function () {
    $fix = makeWorkspaceFixture();
    $a = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$a->number, 'to' => 'ENG-'.$a->number, 'type' => 'blocks',
    ])->assertHasErrors(['Cannot link an issue to itself']);

    expect(IssueRelation::count())->toBe(0);
});

it('rejects a duplicate edge', function () {
    $fix = makeWorkspaceFixture();
    $a = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $b = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$a->number, 'to' => 'ENG-'.$b->number, 'type' => 'blocks',
    ])->assertOk();

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$a->number, 'to' => 'ENG-'.$b->number, 'type' => 'blocks',
    ])->assertHasErrors(['already exists']);

    expect(IssueRelation::count())->toBe(1);
});

it('rejects a related edge that already exists in the reverse direction', function () {
    $fix = makeWorkspaceFixture();
    $a = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $b = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$a->number, 'to' => 'ENG-'.$b->number, 'type' => 'related',
    ])->assertOk();

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$b->number, 'to' => 'ENG-'.$a->number, 'type' => 'related',
    ])->assertHasErrors(['already exists']);

    expect(IssueRelation::count())->toBe(1);
});

it('errors when removing an edge that does not exist', function () {
    $fix = makeWorkspaceFixture();
    $a = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $b = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$a->number, 'to' => 'ENG-'.$b->number, 'type' => 'blocks', 'remove' => true,
    ])->assertHasErrors(['No ']);
});

it('refuses to link an issue that lives in another workspace', function () {
    $fix = makeWorkspaceFixture();
    $a = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    // A second workspace the caller also belongs to, with its own team key.
    $other = Workspace::factory()->create(['owner_user_id' => $fix['user']->id]);
    WorkspaceMember::create([
        'workspace_id' => $other->id,
        'user_id' => $fix['user']->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $otherTeam = Team::factory()->create([
        'workspace_id' => $other->id, 'key' => 'OPS', 'name' => 'Ops',
    ]);

    AimsServer::actingAs($fix['user'])->tool(IssuesLink::class, [
        'from' => 'ENG-'.$a->number,
        'to' => 'OPS-1',
        'type' => 'blocks',
        'workspace_slug' => $fix['workspace']->slug,
    ])->assertHasErrors(["Team 'OPS' not found"]);

    expect($otherTeam->exists)->toBeTrue()
        ->and(IssueRelation::count())->toBe(0);
});
