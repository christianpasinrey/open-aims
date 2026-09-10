<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Models\GraphReference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'List the graphs attached to an issue, project or milestone: id, title, stage, version, repo, ref, summary, '
    .'contexts and reference / edge counts. Compact on purpose — call graphs-get for the references themselves.'
)]
class GraphsList extends Tool
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

        $graphs = Graph::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->withCount(['references', 'edges'])
            ->orderBy('title')
            ->get();

        $contexts = GraphReference::query()
            ->whereIn('graph_id', $graphs->pluck('id'))
            ->whereNotNull('context')
            ->select(['graph_id', 'context'])
            ->distinct()
            ->orderBy('context')
            ->get()
            ->groupBy('graph_id');

        return Response::json([
            'owner' => $this->describeOwner($owner->getMorphClass(), (int) $owner->getKey()),
            'count' => $graphs->count(),
            'graphs' => $graphs->map(fn (Graph $graph): array => [
                'id' => $graph->id,
                'title' => $graph->title,
                'stage' => $graph->stage,
                'version' => $graph->version,
                'repo' => $graph->repo,
                'ref' => $graph->ref,
                'summary' => $graph->summary,
                'contexts' => $contexts->get($graph->id)?->pluck('context')->values()->all() ?? [],
                'references' => (int) $graph->references_count,
                'edges' => (int) $graph->edges_count,
                'updated_at' => $graph->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->required()->description('issue | project | milestone'),
            'owner' => $schema->string()->required()->description('Issue identifier, project slug, or milestone id / name.'),
            'project_slug' => $schema->string()->description('Project of the milestone when owner is a milestone name.'),
            'workspace_slug' => $schema->string()->description('Workspace slug; see `current`.'),
        ];
    }
}
