<?php

declare(strict_types=1);

use App\Modules\Graphs\Actions\IngestGraph;
use App\Modules\Graphs\Exceptions\InvalidGraphPayload;
use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Models\GraphEdge;
use App\Modules\Graphs\Models\GraphNode;
use App\Modules\Graphs\Models\GraphReference;
use App\Modules\Projects\Models\Project;

if (! function_exists('graphPayload')) {
    function graphPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Milestones',
            'summary' => 'Link issues to milestones.',
            'stage' => 'implemented',
            'repo' => 'open-aims',
            'ref' => 'feat/issue-milestones',
            'references' => [graphReference()],
            'resources' => [],
            'edges' => [],
        ], $overrides);
    }

    function graphReference(array $overrides = []): array
    {
        return array_merge([
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
            'functions' => [
                graphFunction(),
                graphFunction([
                    'name' => 'schema',
                    'signature' => 'schema(JsonSchema $schema): array',
                    'summary' => 'Declares the milestone parameter.',
                ]),
            ],
        ], $overrides);
    }

    function graphFunction(array $overrides = []): array
    {
        return array_merge([
            'name' => 'handle',
            'signature' => 'handle(Request $request): Response',
            'visibility' => 'public',
            'summary' => 'Applies the partial update.',
        ], $overrides);
    }

    function resolverReference(): array
    {
        return graphReference([
            'id' => 'resolver',
            'file' => 'app/Modules/Issues/Mcp/Tools/ResolvesIssueRefs.php',
            'class' => 'ResolvesIssueRefs',
            'class_type' => 'trait',
            'summary' => 'Adds the milestone lookup.',
            'functions' => [graphFunction([
                'name' => 'resolveProjectMilestone',
                'signature' => 'resolveProjectMilestone(?int $projectId, string $reference): array',
                'visibility' => 'private',
                'summary' => 'Finds a milestone by id or name inside a project.',
            ])],
        ]);
    }
}

it('creates canonical file, class and function nodes with their hierarchy', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    $graph = app(IngestGraph::class)($issue, graphPayload(), $fix['user']->id);

    $base = 'open-aims:app/Modules/Issues/Mcp/Tools/IssuesUpdate.php';
    $file = GraphNode::where('key', $base)->firstOrFail();
    $class = GraphNode::where('key', $base.'#IssuesUpdate')->firstOrFail();
    $handle = GraphNode::where('key', $base.'#IssuesUpdate::handle')->firstOrFail();

    expect(GraphNode::count())->toBe(4)
        ->and($file->kind)->toBe('file')
        ->and($file->language)->toBe('php')
        ->and($file->parent_node_id)->toBeNull()
        ->and($class->kind)->toBe('class')
        ->and($class->parent_node_id)->toBe($file->id)
        ->and($class->namespace)->toBe('App\\Modules\\Issues\\Mcp\\Tools')
        ->and($class->class_type)->toBe('class')
        ->and($handle->kind)->toBe('function')
        ->and($handle->parent_node_id)->toBe($class->id)
        ->and($handle->signature)->toBe('handle(Request $request): Response')
        ->and($handle->visibility)->toBe('public')
        ->and($handle->context)->toBe('backend')
        ->and($graph->version)->toBe(1)
        ->and($graph->stage)->toBe('implemented')
        ->and($graph->owner_type)->toBe($issue->getMorphClass())
        ->and($graph->owner_id)->toBe($issue->id)
        ->and($graph->created_by_user_id)->toBe($fix['user']->id)
        ->and(GraphReference::where('graph_id', $graph->id)->count())->toBe(4);
});

it('shares nodes between graphs and keeps the texts of each graph', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $ingest = app(IngestGraph::class);

    $first = $ingest($issue, graphPayload(), null);
    $second = $ingest($project, graphPayload([
        'title' => 'Roadmap',
        'references' => [graphReference([
            'summary' => 'Second look at the tool.',
            'functions' => [graphFunction(['summary' => 'Handles it the second way.'])],
        ])],
    ]), null);

    $handle = GraphNode::where('key', 'open-aims:app/Modules/Issues/Mcp/Tools/IssuesUpdate.php#IssuesUpdate::handle')->firstOrFail();

    expect(GraphNode::count())->toBe(4)
        ->and($handle->summary)->toBe('Handles it the second way.')
        ->and(GraphReference::where('graph_id', $first->id)->where('graph_node_id', $handle->id)->value('summary'))
        ->toBe('Applies the partial update.')
        ->and(GraphReference::where('graph_id', $second->id)->where('graph_node_id', $handle->id)->value('summary'))
        ->toBe('Handles it the second way.');
});

it('replaces references and edges when a graph is sent again with the same title', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $ingest = app(IngestGraph::class);

    $ingest($issue, graphPayload([
        'references' => [graphReference(), resolverReference()],
        'edges' => [['from' => 'update#handle', 'to' => 'resolver#resolveProjectMilestone', 'relation' => 'calls']],
    ]), null);

    $graph = $ingest($issue, graphPayload(['stage' => 'observed']), null);

    expect(Graph::count())->toBe(1)
        ->and($graph->version)->toBe(2)
        ->and($graph->stage)->toBe('observed')
        ->and(GraphReference::where('graph_id', $graph->id)->count())->toBe(4)
        ->and(GraphEdge::where('graph_id', $graph->id)->count())->toBe(0);
});

it('stores resources and links edges to references, resources and existing canonical keys', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $project = Project::factory()->create(['workspace_id' => $fix['workspace']->id]);
    $ingest = app(IngestGraph::class);

    $ingest($issue, graphPayload([
        'resources' => [[
            'id' => 'column',
            'type' => 'column',
            'name' => 'issues.project_milestone_id',
            'description' => 'Milestone an issue belongs to.',
            'change' => 'added',
        ]],
        'edges' => [['from' => 'update#handle', 'to' => 'column', 'relation' => 'writes']],
    ]), null);

    $column = GraphNode::where('key', 'open-aims:resource:column:issues.project_milestone_id')->firstOrFail();
    $handle = GraphNode::where('key', 'open-aims:app/Modules/Issues/Mcp/Tools/IssuesUpdate.php#IssuesUpdate::handle')->firstOrFail();

    expect($column->kind)->toBe('resource')
        ->and($column->resource_type)->toBe('column')
        ->and(GraphEdge::where('source_node_id', $handle->id)->where('target_node_id', $column->id)->where('relation', 'writes')->exists())
        ->toBeTrue();

    $graph = $ingest($project, graphPayload([
        'title' => 'Uses the tool',
        'references' => [resolverReference()],
        'edges' => [[
            'from' => 'open-aims:app/Modules/Issues/Mcp/Tools/IssuesUpdate.php#IssuesUpdate::handle',
            'to' => 'resolver#resolveProjectMilestone',
            'relation' => 'calls',
        ]],
    ]), null);

    expect(GraphEdge::where('graph_id', $graph->id)->where('source_node_id', $handle->id)->where('relation', 'calls')->exists())
        ->toBeTrue();
});

it('rejects a reference missing a required field and names it', function (string $field) {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $reference = graphReference();
    unset($reference[$field]);

    expect(fn () => app(IngestGraph::class)($issue, graphPayload(['references' => [$reference]]), null))
        ->toThrow(InvalidGraphPayload::class, "references[update].{$field}");

    expect(Graph::count())->toBe(0)->and(GraphNode::count())->toBe(0);
})->with(['file', 'context', 'role', 'change', 'description', 'purpose', 'summary', 'functions']);

it('rejects a function missing name, signature, visibility or summary', function (string $field) {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $function = graphFunction();
    unset($function[$field]);

    expect(fn () => app(IngestGraph::class)($issue, graphPayload([
        'references' => [graphReference(['functions' => [$function]])],
    ]), null))->toThrow(InvalidGraphPayload::class, "references[update].functions.0.{$field}");
})->with(['name', 'signature', 'visibility', 'summary']);

it('requires namespace and class_type when a class is given', function (string $field) {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);
    $reference = graphReference();
    unset($reference[$field]);

    expect(fn () => app(IngestGraph::class)($issue, graphPayload(['references' => [$reference]]), null))
        ->toThrow(InvalidGraphPayload::class, "references[update].{$field}");
})->with(['namespace', 'class_type']);

it('rejects unsafe file paths', function (string $path) {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    expect(fn () => app(IngestGraph::class)($issue, graphPayload([
        'references' => [graphReference(['file' => $path])],
    ]), null))->toThrow(InvalidGraphPayload::class, 'references[update].file');
})->with([
    'parent traversal' => '../etc/passwd',
    'absolute' => '/app/Models/Issue.php',
    'inner traversal' => 'app/Models/../Issue.php',
    'backslashes' => 'app\\Models\\Issue.php',
    'hash' => 'app/Models/Issue.php#Issue',
]);

it('rejects an edge endpoint that is neither in the graph nor an existing key', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    expect(fn () => app(IngestGraph::class)($issue, graphPayload([
        'edges' => [['from' => 'update#handle', 'to' => 'ghost#run', 'relation' => 'calls']],
    ]), null))->toThrow(InvalidGraphPayload::class, 'edges.0.to');

    expect(Graph::count())->toBe(0);
});

it('rejects values outside the vocabulary', function (array $overrides, string $path) {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    expect(fn () => app(IngestGraph::class)($issue, graphPayload($overrides), null))
        ->toThrow(InvalidGraphPayload::class, $path);
})->with([
    'stage' => [['stage' => 'done'], 'stage'],
    'context' => [['references' => [graphReference(['context' => 'web'])]], 'references[update].context'],
    'role' => [['references' => [graphReference(['role' => 'helper'])]], 'references[update].role'],
    'relation' => [[
        'references' => [graphReference(), resolverReference()],
        'edges' => [['from' => 'update', 'to' => 'resolver', 'relation' => 'owns']],
    ], 'edges.0.relation'],
]);

it('rejects duplicated reference ids', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    expect(fn () => app(IngestGraph::class)($issue, graphPayload([
        'references' => [graphReference(), graphReference(['file' => 'app/Other.php'])],
    ]), null))->toThrow(InvalidGraphPayload::class, 'references[update].id');
});

it('keeps the nodes of each workspace apart', function () {
    $a = makeWorkspaceFixture();
    $issueA = makeIssue($a['team'], $a['workspace'], $a['states']['Todo']);
    app(IngestGraph::class)($issueA, graphPayload(), null);

    $b = makeWorkspaceFixture();
    $issueB = makeIssue($b['team'], $b['workspace'], $b['states']['Todo']);
    app(IngestGraph::class)($issueB, graphPayload(), null);

    expect(GraphNode::count())->toBe(4)
        ->and(GraphNode::withoutGlobalScopes()->count())->toBe(8);
});
