<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Graphs\Exceptions\GraphScopeNotFound;
use App\Modules\Graphs\Models\GraphNode;
use App\Modules\Graphs\Support\GraphMapQuery;
use App\Modules\Graphs\Support\GraphScope;
use App\Modules\Graphs\Support\GraphVocabulary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Ask the merged workspace map before touching code. '
    .'mode=node: one node by key, or by file (+class, +function) or namespace — with what it contains and every '
    .'issue, milestone or project graph that referenced it (several matches come back as a list). '
    .'mode=neighbors: nodes up to depth 1–3 around a node, with the relations between them. '
    .'mode=path: shortest connection between a node and `to` (a key). '
    .'mode=hotspots: most referenced nodes, filterable by context and kind. '
    .'mode=subgraph: what a project (project_slug) or milestone (milestone id) contributed, including its issues. '
    .'Keys look like "open-aims:app/Models/Issue.php#Issue::milestone" — see graphs-schema.'
)]
class GraphsMap extends Tool
{
    use ResolvesGraphOwner;
    use ResolvesWorkspace;

    private const MODES = ['node', 'neighbors', 'path', 'hotspots', 'subgraph'];

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $data = Validator::make($request->all(), [
            'mode' => ['required', 'string', Rule::in(self::MODES)],
            'key' => ['nullable', 'string', 'max:1100'],
            'file' => ['nullable', 'string', 'max:512'],
            'class' => ['nullable', 'string', 'max:255'],
            'function' => ['nullable', 'string', 'max:255'],
            'namespace' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:1100'],
            'depth' => ['nullable', 'integer', 'min:1', 'max:'.GraphMapQuery::MAX_DEPTH],
            'context' => ['nullable', Rule::in(GraphVocabulary::CONTEXTS)],
            'kind' => ['nullable', Rule::in(['file', 'class', 'function', 'resource'])],
            'project_slug' => ['nullable', 'string', 'max:200'],
            'milestone' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $query = new GraphMapQuery((int) $workspace->id);

        return match ($data['mode']) {
            'node' => $this->node($query, $data),
            'neighbors' => $this->neighbors($query, $data),
            'path' => $this->path($query, $data),
            'hotspots' => Response::json([
                'hotspots' => $query->hotspots($data['context'] ?? null, $data['kind'] ?? null, (int) ($data['limit'] ?? 20)),
            ]),
            default => $this->subgraph($query, (int) $workspace->id, $data),
        };
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function node(GraphMapQuery $query, array $data): Response
    {
        $nodes = $query->find($this->criteria($data));
        if ($nodes->isEmpty()) {
            return Response::error($this->notFound($data));
        }

        if ($nodes->count() > 1) {
            return Response::json(['count' => $nodes->count(), 'nodes' => $query->listing($nodes)]);
        }

        $described = $query->describe($nodes->first());
        $described['referenced_by'] = array_map(fn (array $reference): array => [
            'owner' => $this->describeOwner((string) $reference['owner_type'], (int) $reference['owner_id']),
        ] + Arr::except($reference, ['owner_type', 'owner_id']), $described['referenced_by']);

        return Response::json($described);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function neighbors(GraphMapQuery $query, array $data): Response
    {
        [$node, $error] = $this->single($query, $data);
        if ($node === null) {
            return Response::error((string) $error);
        }

        return Response::json($query->neighbors($node, (int) ($data['depth'] ?? 1), $data['context'] ?? null));
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function path(GraphMapQuery $query, array $data): Response
    {
        [$from, $error] = $this->single($query, $data);
        if ($from === null) {
            return Response::error((string) $error);
        }

        if (empty($data['to'])) {
            return Response::error('mode=path needs `to`: the canonical key of the destination node.');
        }

        $to = $query->find(['key' => $data['to']])->first();
        if ($to === null) {
            return Response::error("Node '{$data['to']}' not found in the workspace map.");
        }

        $path = $query->path($from, $to);
        if ($path === null) {
            return Response::error(sprintf(
                "No path between '%s' and '%s' within %d steps.",
                $from->key,
                $to->key,
                GraphMapQuery::MAX_PATH,
            ));
        }

        return Response::json(['from' => $from->key, 'to' => $to->key] + $path);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function subgraph(GraphMapQuery $query, int $workspaceId, array $data): Response
    {
        if (empty($data['project_slug']) && empty($data['milestone'])) {
            return Response::error('mode=subgraph needs project_slug or milestone.');
        }

        try {
            $graphIds = GraphScope::graphIds(
                $workspaceId,
                $data['project_slug'] ?? null,
                isset($data['milestone']) ? (int) $data['milestone'] : null,
            );
        } catch (GraphScopeNotFound $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::json($query->subgraph($graphIds, $data['context'] ?? null));
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{0: ?GraphNode, 1: ?string}
     */
    private function single(GraphMapQuery $query, array $data): array
    {
        $nodes = $query->find($this->criteria($data));

        return match (true) {
            $nodes->isEmpty() => [null, $this->notFound($data)],
            $nodes->count() > 1 => [null, 'Several nodes match; pass `key`. Matches: '
                .$nodes->take(10)->pluck('key')->implode(', ')],
            default => [$nodes->first(), null],
        };
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,?string>
     */
    private function criteria(array $data): array
    {
        return Arr::only($data, ['key', 'file', 'class', 'function', 'namespace']);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function notFound(array $data): string
    {
        $criteria = array_filter($this->criteria($data));

        if ($criteria === []) {
            return 'Pass key, or file (+class, +function), or namespace to find a node.';
        }

        return 'Node '.json_encode($criteria, JSON_UNESCAPED_SLASHES).' not found in the workspace map.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()->required()->description('node | neighbors | path | hotspots | subgraph'),
            'key' => $schema->string()->description('Canonical node key.'),
            'file' => $schema->string()->description('Repo-relative file path (alternative to key).'),
            'class' => $schema->string()->description('Class name, with or without file.'),
            'function' => $schema->string()->description('Function name, with file and/or class.'),
            'namespace' => $schema->string()->description('Namespace: lists its classes.'),
            'to' => $schema->string()->description('mode=path: canonical key of the destination.'),
            'depth' => $schema->integer()->description('mode=neighbors: 1 to 3 (default 1).'),
            'context' => $schema->string()->description('Filter by context (neighbors, hotspots, subgraph).'),
            'kind' => $schema->string()->description('mode=hotspots: file | class | function | resource.'),
            'project_slug' => $schema->string()->description('mode=subgraph: project.'),
            'milestone' => $schema->integer()->description('mode=subgraph: milestone id.'),
            'limit' => $schema->integer()->description('mode=hotspots: 1 to 100 (default 20).'),
            'workspace_slug' => $schema->string()->description('Workspace slug; see `current`.'),
        ];
    }
}
