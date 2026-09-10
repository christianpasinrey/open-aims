<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Graphs\Actions\IngestGraph;
use App\Modules\Graphs\Exceptions\InvalidGraphPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Attach a graph of code references to an issue, project or milestone. Nodes are canonical per '
    .'workspace (file, class, function, resource), so graphs from different work merge into one map. '
    .'Sending a graph again with the same title for the same owner REPLACES it and bumps its version. '
    .'Graph: title, summary, stage (planned before coding | implemented after | observed for existing code), '
    .'repo (e.g. "open-aims"), ref (branch or commit). '
    .'Every reference needs: id, file (repo-relative path), context, role, change (added|modified|removed|read), '
    .'description, purpose, summary and functions (may be empty). With class: namespace and class_type too. '
    .'Every function needs: name, signature, visibility, summary. '
    .'resources[] (tables, columns, routes, events…) need id, type, name, description, change. '
    .'edges[]: from/to as reference id, "refId#function", resource id or an existing canonical key; '
    .'relation in calls, reads, writes, dispatches, listens, renders, extends, implements, uses, tests, routes_to, migrates. '
    .'Read aims://guides/graphs for vocabularies and a full example.'
)]
class GraphsAttach extends Tool
{
    use ResolvesGraphOwner;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $data = Validator::make($request->all(), [
            'owner_type' => 'required|string|in:issue,project,milestone',
            'owner' => 'required|string|max:255',
            'project_slug' => 'nullable|string|max:200',
        ])->validate();

        [$owner, $error] = $this->resolveOwner($workspace, $data['owner_type'], $data['owner'], $data['project_slug'] ?? null);
        if ($owner === null) {
            return Response::error((string) $error);
        }

        $payload = Arr::only($request->all(), ['title', 'summary', 'stage', 'repo', 'ref', 'references', 'resources', 'edges']);
        $userId = auth()->id();

        try {
            $graph = app(IngestGraph::class)($owner, $payload, $userId !== null ? (int) $userId : null);
        } catch (InvalidGraphPayload $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::json([
            'id' => $graph->id,
            'owner' => $this->describeOwner($graph->owner_type, $graph->owner_id),
            'title' => $graph->title,
            'stage' => $graph->stage,
            'version' => $graph->version,
            'references' => $graph->references()->count(),
            'edges' => $graph->edges()->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->required()->description('issue | project | milestone'),
            'owner' => $schema->string()->required()->description(
                'Issue identifier ("DER-69"), project slug, or milestone id / name (a name needs project_slug).'
            ),
            'project_slug' => $schema->string()->description('Project of the milestone when owner is a milestone name.'),
            'title' => $schema->string()->required()->description('Unique per owner; the same title replaces the graph.'),
            'summary' => $schema->string()->required()->description('What this work changes, in one or two sentences.'),
            'stage' => $schema->string()->required()->description('planned | implemented | observed'),
            'repo' => $schema->string()->required()->description('Repository prefix for node keys, e.g. "open-aims".'),
            'ref' => $schema->string()->required()->description('Branch or commit the references point at.'),
            'references' => $schema->array()->required()->description(
                'Code references: {id, file, context, role, change, description, purpose, summary, functions[], '
                .'namespace?, class?, class_type?, extends?, implements?[], language?}. '
                .'functions[]: {name, signature, visibility, summary, change?, lines?[start,end], side_effects?[], throws?[]}.'
            ),
            'resources' => $schema->array()->description('Non-code nodes: {id, type, name, description, change}.'),
            'edges' => $schema->array()->description('{from, to, relation, label?}.'),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — when omitted the FIRST '
                .'membership by id is used, which may not be the one the user means. Get valid slugs from `current`.'
            ),
        ];
    }
}
