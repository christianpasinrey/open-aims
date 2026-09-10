<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Graphs\Models\Graph;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Delete a graph with its references and edges. Canonical nodes stay, because other graphs may still use them. '
    .'Requires confirm=true.'
)]
class GraphsDelete extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $data = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
            'confirm' => 'nullable|boolean',
        ])->validate();

        $graph = Graph::query()->find((int) $data['id']);
        if ($graph === null) {
            return Response::error("Graph {$data['id']} not found.");
        }

        if (($data['confirm'] ?? false) !== true) {
            return Response::error("Pass confirm=true to delete graph {$graph->id} ('{$graph->title}').");
        }

        DB::transaction(function () use ($graph): void {
            $graph->references()->delete();
            $graph->edges()->delete();
            $graph->delete();
        });

        return Response::json([
            'deleted' => true,
            'id' => $graph->id,
            'title' => $graph->title,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('Graph id.'),
            'confirm' => $schema->boolean()->description('Must be true.'),
            'workspace_slug' => $schema->string()->description('Workspace slug; see `current`.'),
        ];
    }
}
