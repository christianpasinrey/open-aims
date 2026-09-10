<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Models\GraphEdge;
use App\Modules\Graphs\Models\GraphReference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Fetch one graph with every reference (canonical key, kind, file, namespace, class, function, signature, '
    .'visibility, resource, context, role, change, description, purpose, summary) and every edge as canonical keys. '
    .'Use the keys as edge endpoints in graphs-attach to link new graphs to nodes that already exist.'
)]
class GraphsGet extends Tool
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
            'id' => 'required|integer|min:1',
        ])->validate();

        $graph = Graph::query()
            ->with(['references.node', 'edges.source:id,key', 'edges.target:id,key'])
            ->find((int) $data['id']);
        if ($graph === null) {
            return Response::error("Graph {$data['id']} not found.");
        }

        return Response::json([
            'id' => $graph->id,
            'owner' => $this->describeOwner($graph->owner_type, $graph->owner_id),
            'title' => $graph->title,
            'summary' => $graph->summary,
            'stage' => $graph->stage,
            'repo' => $graph->repo,
            'ref' => $graph->ref,
            'version' => $graph->version,
            'updated_at' => $graph->updated_at?->toIso8601String(),
            'references' => $graph->references
                ->sortBy(fn (GraphReference $reference) => $reference->node?->key)
                ->values()
                ->map(fn (GraphReference $reference): array => [
                    'key' => $reference->node?->key,
                    'kind' => $reference->node?->kind,
                    'file' => $reference->node?->file_path,
                    'namespace' => $reference->node?->namespace,
                    'class' => $reference->node?->class_name,
                    'function' => $reference->node?->function_name,
                    'signature' => $reference->node?->signature,
                    'visibility' => $reference->node?->visibility,
                    'resource_type' => $reference->node?->resource_type,
                    'resource_name' => $reference->node?->resource_name,
                    'context' => $reference->context,
                    'role' => $reference->role,
                    'change' => $reference->change,
                    'description' => $reference->description,
                    'purpose' => $reference->purpose,
                    'summary' => $reference->summary,
                    'meta' => $reference->meta,
                ])
                ->all(),
            'edges' => $graph->edges->map(fn (GraphEdge $edge): array => [
                'from' => $edge->source?->key,
                'to' => $edge->target?->key,
                'relation' => $edge->relation,
                'label' => $edge->label,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('Graph id from graphs-list or graphs-attach.'),
            'workspace_slug' => $schema->string()->description('Workspace slug; see `current`.'),
        ];
    }
}
