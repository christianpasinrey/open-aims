<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Support\GraphImpact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Impact of a graph on the workspace map — call it right after graphs-attach stage=planned. '
    .'Seeds are the references the graph marks modified or removed. Returns: affected nodes (whoever calls, '
    .'reads, listens to, renders, extends, implements or tests a seed, plus readers of data a seed writes and '
    .'listeners of events it dispatches) with their distance and the relation that reaches them; counts by '
    .'context and role; tests_to_run; collisions with open issues, milestones or projects whose graphs touch the '
    .'same nodes; and signature_changes against what other graphs recorded. depth 1–3 (default 2).'
)]
class GraphsImpact extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $data = Validator::make($request->all(), [
            'graph_id' => ['required', 'integer', 'min:1'],
            'depth' => ['nullable', 'integer', 'min:1', 'max:'.GraphImpact::MAX_DEPTH],
        ])->validate();

        $graph = Graph::query()->find((int) $data['graph_id']);
        if ($graph === null) {
            return Response::error("Graph {$data['graph_id']} not found.");
        }

        return Response::json(app(GraphImpact::class)->analyse($graph, (int) ($data['depth'] ?? 2)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'graph_id' => $schema->integer()->required()->description('Graph to analyse (usually the planned one).'),
            'depth' => $schema->integer()->description('How many steps to follow: 1 to 3, default 2.'),
            'workspace_slug' => $schema->string()->description('Workspace slug; see `current`.'),
        ];
    }
}
