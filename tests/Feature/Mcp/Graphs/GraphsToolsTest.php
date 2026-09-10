<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Graphs\Mcp\Tools\GraphsAttach;
use App\Modules\Graphs\Mcp\Tools\GraphsDelete;
use App\Modules\Graphs\Mcp\Tools\GraphsGet;
use App\Modules\Graphs\Mcp\Tools\GraphsList;
use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Models\GraphNode;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;

if (! function_exists('toolGraphPayload')) {
    function toolGraphPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Milestones',
            'summary' => 'Link issues to milestones.',
            'stage' => 'planned',
            'repo' => 'open-aims',
            'ref' => 'feat/issue-milestones',
            'references' => [[
                'id' => 'update',
                'file' => 'app/Modules/Issues/Mcp/Tools/IssuesUpdate.php',
                'context' => 'backend',
                'role' => 'mcp_tool',
                'change' => 'modified',
                'namespace' => 'App\\Modules\\Issues\\Mcp\\Tools',
                'class' => 'IssuesUpdate',
                'class_type' => 'class',
                'description' => 'MCP tool that partially updates an issue.',
                'purpose' => 'Entry point for Claude to change an issue.',
                'summary' => 'Resolves and stores the milestone.',
                'functions' => [[
                    'name' => 'handle',
                    'signature' => 'handle(Request $request): Response',
                    'visibility' => 'public',
                    'summary' => 'Applies the partial update.',
                ]],
            ]],
            'resources' => [[
                'id' => 'column',
                'type' => 'column',
                'name' => 'issues.project_milestone_id',
                'description' => 'Milestone an issue belongs to.',
                'change' => 'added',
            ]],
            'edges' => [['from' => 'update#handle', 'to' => 'column', 'relation' => 'writes']],
        ], $overrides);
    }
}

it('attaches a graph to an issue and reports its size', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, [
        'owner_type' => 'issue',
        'owner' => 'ENG-'.$issue->number,
    ] + toolGraphPayload())
        ->assertOk()
        ->assertSee(['"title":"Milestones"', '"stage":"planned"', '"version":1', '"references":4', '"edges":1']);

    $graph = Graph::firstOrFail();
    expect($graph->owner_id)->toBe($issue->id)
        ->and($graph->created_by_user_id)->toBe($fix['user']->id);
});

it('attaches a graph to a project by slug', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, [
        'owner_type' => 'project',
        'owner' => $project->slug,
    ] + toolGraphPayload())->assertOk();

    expect(Graph::firstOrFail()->owner_id)->toBe($project->id);
});

it('attaches a graph to a milestone by name within a project', function () {
    $fix = makeWorkspaceFixture();
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $milestone = ProjectMilestone::create(['project_id' => $project->id, 'name' => 'Alpha', 'sort_order' => 0]);

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, [
        'owner_type' => 'milestone',
        'owner' => 'alpha',
        'project_slug' => $project->slug,
    ] + toolGraphPayload())->assertOk();

    $graph = Graph::firstOrFail();
    expect($graph->owner_type)->toBe($milestone->getMorphClass())
        ->and($graph->owner_id)->toBe($milestone->id);
});

it('returns the ingest problems as a tool error', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $payload = toolGraphPayload();
    unset($payload['references'][0]['purpose']);

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, [
        'owner_type' => 'issue',
        'owner' => 'ENG-'.$issue->number,
    ] + $payload)->assertHasErrors(['references[update].purpose']);

    expect(Graph::count())->toBe(0);
});

it('reports an owner that does not exist', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, [
        'owner_type' => 'issue',
        'owner' => 'ENG-999',
    ] + toolGraphPayload())->assertHasErrors(['ENG-999 not found']);
});

it('lists the graphs of an owner without their full content', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $identifier = 'ENG-'.$issue->number;

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, ['owner_type' => 'issue', 'owner' => $identifier] + toolGraphPayload())->assertOk();

    AimsServer::actingAs($fix['user'])->tool(GraphsList::class, [
        'owner_type' => 'issue',
        'owner' => $identifier,
    ])
        ->assertOk()
        ->assertSee(['"count":1', '"title":"Milestones"', '"contexts":["backend"]', '"references":4'])
        ->assertDontSee('Entry point for Claude to change an issue.');
});

it('gets a graph with its references and edges', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, ['owner_type' => 'issue', 'owner' => 'ENG-'.$issue->number] + toolGraphPayload())->assertOk();
    $graph = Graph::firstOrFail();

    AimsServer::actingAs($fix['user'])->tool(GraphsGet::class, ['id' => $graph->id])
        ->assertOk()
        ->assertSee([
            '"kind":"function"',
            '"function":"handle"',
            '"visibility":"public"',
            '"change":"modified"',
            '"purpose":"Entry point for Claude to change an issue."',
            '"resource_type":"column"',
            '"relation":"writes"',
        ]);
});

it('deletes a graph but keeps nodes that other graphs still use', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);

    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, ['owner_type' => 'issue', 'owner' => 'ENG-'.$issue->number] + toolGraphPayload())->assertOk();
    AimsServer::actingAs($fix['user'])->tool(GraphsAttach::class, ['owner_type' => 'project', 'owner' => $project->slug] + toolGraphPayload())->assertOk();
    $issueGraph = Graph::where('owner_id', $issue->id)->firstOrFail();
    $nodes = GraphNode::count();

    AimsServer::actingAs($fix['user'])->tool(GraphsDelete::class, ['id' => $issueGraph->id])
        ->assertHasErrors(['confirm']);

    AimsServer::actingAs($fix['user'])->tool(GraphsDelete::class, ['id' => $issueGraph->id, 'confirm' => true])
        ->assertOk();

    expect(Graph::count())->toBe(1)->and(GraphNode::count())->toBe($nodes);
});

it('does not reach graphs of another workspace', function () {
    $outsider = makeWorkspaceFixture();
    $outsiderIssue = makeIssue($outsider['team'], $outsider['workspace'], $outsider['states']['Todo']);
    AimsServer::actingAs($outsider['user'])->tool(GraphsAttach::class, ['owner_type' => 'issue', 'owner' => 'ENG-'.$outsiderIssue->number] + toolGraphPayload())->assertOk();
    $foreign = Graph::withoutGlobalScopes()->firstOrFail();

    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(GraphsGet::class, ['id' => $foreign->id])
        ->assertHasErrors(['not found']);
    AimsServer::actingAs($fix['user'])->tool(GraphsDelete::class, ['id' => $foreign->id, 'confirm' => true])
        ->assertHasErrors(['not found']);

    expect(Graph::withoutGlobalScopes()->count())->toBe(1);
});
