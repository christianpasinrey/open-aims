<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Support;

use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Models\GraphEdge;
use App\Modules\Graphs\Models\GraphNode;
use App\Modules\Graphs\Models\GraphReference;
use Illuminate\Support\Collection;

/**
 * Turns stored graphs into the `{nodes, links}` shape the viewer draws.
 *
 * Parents (file, class) are always included so a function never floats
 * alone, and containment is emitted as `contains` links. On the map, nodes
 * and edges carry how many graphs assert them.
 */
final class GraphView
{
    /**
     * Human label of a node: file basename, class, Class::function or type:name.
     */
    public static function labelOf(GraphNode $node): string
    {
        return match ($node->kind) {
            'file' => basename((string) $node->file_path),
            'class' => (string) $node->class_name,
            'function' => $node->class_name !== null
                ? $node->class_name.'::'.$node->function_name
                : (string) $node->function_name,
            'resource' => $node->resource_type.':'.$node->resource_name,
            default => (string) $node->key,
        };
    }

    /**
     * A graph without its nodes: Graphs sections list these as tabs and fetch
     * the nodes of the one selected.
     *
     * @return array{id:int,title:string,summary:string,stage:string,version:int,repo:string,ref:string,updated_at:string|null}
     */
    public static function summaryOf(Graph $graph): array
    {
        return [
            'id' => (int) $graph->id,
            'title' => (string) $graph->title,
            'summary' => (string) $graph->summary,
            'stage' => (string) $graph->stage,
            'version' => (int) $graph->version,
            'repo' => (string) $graph->repo,
            'ref' => (string) $graph->ref,
            'updated_at' => $graph->updated_at?->toIso8601String(),
        ];
    }

    /**
     * One graph as its owner sees it: node texts and change come from this
     * graph's references.
     *
     * @return array{nodes: list<array<string,mixed>>, links: list<array<string,mixed>>}
     */
    public function forGraph(Graph $graph): array
    {
        $references = $graph->references->keyBy('graph_node_id');
        $edges = $graph->edges;

        $nodes = $this->withAncestors(
            (int) $graph->workspace_id,
            $references->keys()
                ->merge($edges->pluck('source_node_id'))
                ->merge($edges->pluck('target_node_id')),
            $graph->references->pluck('node')->filter(),
        );
        $graphCounts = $this->graphCounts($nodes->keys());

        return [
            'nodes' => $nodes->map(fn (GraphNode $node): array => $this->node(
                $node,
                $references->get($node->id),
                $graphCounts->get($node->id, 0),
            ))->values()->all(),
            'links' => array_merge(
                $edges->map(static fn (GraphEdge $edge): array => [
                    'source' => $edge->source_node_id,
                    'target' => $edge->target_node_id,
                    'relation' => $edge->relation,
                    'label' => $edge->label,
                    'weight' => 1,
                ])->values()->all(),
                $this->containment($nodes),
            ),
        ];
    }

    /**
     * Several graphs merged: latest node values, weights by number of graphs.
     *
     * @param  Collection<int,int>  $graphIds
     * @return array{nodes: list<array<string,mixed>>, links: list<array<string,mixed>>, stats: array<string,int>}
     */
    public function forMap(int $workspaceId, Collection $graphIds, ?string $context = null): array
    {
        $references = GraphReference::query()
            ->whereIn('graph_id', $graphIds)
            ->get(['graph_id', 'graph_node_id']);
        $edges = GraphEdge::query()
            ->whereIn('graph_id', $graphIds)
            ->get(['graph_id', 'source_node_id', 'target_node_id', 'relation', 'label']);

        $graphCounts = $references
            ->groupBy('graph_node_id')
            ->map(static fn (Collection $rows): int => $rows->pluck('graph_id')->unique()->count());

        $nodes = $this->withAncestors(
            $workspaceId,
            $graphCounts->keys()
                ->merge($edges->pluck('source_node_id'))
                ->merge($edges->pluck('target_node_id')),
        );

        if ($context !== null) {
            $nodes = $nodes->filter(static fn (GraphNode $node): bool => $node->context === $context);
        }

        $links = $edges
            ->filter(static fn (GraphEdge $edge): bool => $nodes->has($edge->source_node_id) && $nodes->has($edge->target_node_id))
            ->groupBy(static fn (GraphEdge $edge): string => $edge->source_node_id.'|'.$edge->target_node_id.'|'.$edge->relation)
            ->map(static fn (Collection $group): array => [
                'source' => $group->first()->source_node_id,
                'target' => $group->first()->target_node_id,
                'relation' => $group->first()->relation,
                'label' => $group->first()->label,
                'weight' => $group->pluck('graph_id')->unique()->count(),
            ])
            ->values()
            ->all();
        $links = array_merge($links, $this->containment($nodes));

        return [
            'nodes' => $nodes->map(fn (GraphNode $node): array => $this->node(
                $node,
                null,
                $graphCounts->get($node->id, 0),
            ))->values()->all(),
            'links' => $links,
            'stats' => [
                'graphs' => $graphIds->count(),
                'nodes' => $nodes->count(),
                'links' => count($links),
            ],
        ];
    }

    /**
     * @param  Collection<int,int>  $ids
     * @param  Collection<int,GraphNode>|null  $seed  already loaded nodes
     * @return Collection<int,GraphNode> keyed by id
     */
    private function withAncestors(int $workspaceId, Collection $ids, ?Collection $seed = null): Collection
    {
        $nodes = ($seed ?? collect())->keyBy('id');
        $attempted = $nodes->keys();
        $missing = $ids->unique()->diff($attempted)->values();

        while ($missing->isNotEmpty()) {
            GraphNode::query()
                ->withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->whereIn('id', $missing)
                ->get()
                ->each(static fn (GraphNode $node) => $nodes->put($node->id, $node));

            $attempted = $attempted->merge($missing);
            $missing = $nodes->pluck('parent_node_id')->filter()->unique()->diff($attempted)->values();
        }

        return $nodes;
    }

    /**
     * @param  Collection<int,int>  $nodeIds
     * @return Collection<int,int> node id → number of graphs that reference it
     */
    private function graphCounts(Collection $nodeIds): Collection
    {
        return GraphReference::query()
            ->whereIn('graph_node_id', $nodeIds)
            ->selectRaw('graph_node_id, COUNT(DISTINCT graph_id) as total')
            ->groupBy('graph_node_id')
            ->pluck('total', 'graph_node_id')
            ->map(static fn ($total): int => (int) $total);
    }

    /**
     * @param  Collection<int,GraphNode>  $nodes
     * @return list<array<string,mixed>>
     */
    private function containment(Collection $nodes): array
    {
        return $nodes
            ->filter(static fn (GraphNode $node): bool => $node->parent_node_id !== null && $nodes->has($node->parent_node_id))
            ->map(static fn (GraphNode $node): array => [
                'source' => $node->parent_node_id,
                'target' => $node->id,
                'relation' => 'contains',
                'label' => null,
                'weight' => 1,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function node(GraphNode $node, ?GraphReference $reference, int $graphs): array
    {
        return [
            'id' => $node->id,
            'key' => $node->key,
            'kind' => $node->kind,
            'label' => self::labelOf($node),
            'file' => $node->file_path,
            'language' => $node->language,
            'namespace' => $node->namespace,
            'class' => $node->class_name,
            'class_type' => $node->class_type,
            'function' => $node->function_name,
            'signature' => $node->signature,
            'visibility' => $node->visibility,
            'resource_type' => $node->resource_type,
            'resource_name' => $node->resource_name,
            'context' => $reference?->context ?? $node->context,
            'role' => $reference?->role ?? $node->role,
            'change' => $reference?->change,
            'description' => $reference?->description ?? $node->description,
            'purpose' => $reference?->purpose ?? $node->purpose,
            'summary' => $reference?->summary ?? $node->summary,
            'parent_id' => $node->parent_node_id,
            'graphs' => $graphs,
        ];
    }
}
