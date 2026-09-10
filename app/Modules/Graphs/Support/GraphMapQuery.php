<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Support;

use App\Modules\Graphs\Models\GraphEdge;
use App\Modules\Graphs\Models\GraphNode;
use App\Modules\Graphs\Models\GraphReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Questions asked to the merged workspace map: what a node is and who
 * touched it, what surrounds it, how two nodes connect, where the work
 * concentrates, and what a project or milestone contributed.
 *
 * Edges are aggregated across graphs (weight = number of graphs asserting
 * them) and walked in both directions: "connected" matters more than
 * "who called whom" when orienting.
 */
final readonly class GraphMapQuery
{
    public const MAX_DEPTH = 3;

    public const MAX_PATH = 6;

    public function __construct(private int $workspaceId) {}

    /**
     * @param  array{key?:?string,file?:?string,class?:?string,function?:?string,namespace?:?string}  $criteria
     * @return Collection<int,GraphNode>
     */
    public function find(array $criteria): Collection
    {
        $key = $criteria['key'] ?? null;
        if ($key !== null && $key !== '') {
            return $this->nodes()->where('key_hash', NodeKey::hash($key))->get();
        }

        $file = $criteria['file'] ?? null;
        $class = $criteria['class'] ?? null;
        $function = $criteria['function'] ?? null;
        $namespace = $criteria['namespace'] ?? null;

        if ($file === null && $class === null && $function === null && $namespace === null) {
            return collect();
        }

        $query = $this->nodes();
        match (true) {
            $function !== null => $query->where('kind', 'function')->where('function_name', $function),
            $class !== null, $file === null => $query->where('kind', 'class'),
            default => $query->where('kind', 'file'),
        };

        return $query
            ->when($file !== null, fn (Builder $q) => $q->where('file_path', $file))
            ->when($class !== null, fn (Builder $q) => $q->where('class_name', $class))
            ->when($namespace !== null, fn (Builder $q) => $q->where('namespace', $namespace))
            ->orderBy('key')
            ->limit(50)
            ->get();
    }

    /**
     * Several matches at once (e.g. every class of a namespace).
     *
     * @param  Collection<int,GraphNode>  $nodes
     * @return list<array<string,mixed>>
     */
    public function listing(Collection $nodes): array
    {
        return $nodes->map(static fn (GraphNode $node): array => array_filter([
            'key' => $node->key,
            'kind' => $node->kind,
            'label' => GraphView::labelOf($node),
            'file' => $node->file_path,
            'namespace' => $node->namespace,
            'class' => $node->class_name,
            'function' => $node->function_name,
            'context' => $node->context,
            'role' => $node->role,
        ], static fn ($value): bool => $value !== null))->values()->all();
    }

    /**
     * Full node, what it contains (two levels) and every graph that references it.
     *
     * @return array<string,mixed>
     */
    public function describe(GraphNode $node): array
    {
        $references = GraphReference::query()
            ->where('graph_node_id', $node->id)
            ->with('graph:id,title,stage,owner_type,owner_id,updated_at')
            ->get();

        return [
            'node' => $this->full($node),
            'contains' => $this->descendants($node, 2)->map(fn (GraphNode $child): array => $this->compact($child))->values()->all(),
            'referenced_by' => $references->map(static fn (GraphReference $reference): array => [
                'graph_id' => $reference->graph_id,
                'graph' => $reference->graph?->title,
                'stage' => $reference->graph?->stage,
                'change' => $reference->change,
                'summary' => $reference->summary,
                'owner_type' => $reference->graph?->owner_type,
                'owner_id' => $reference->graph?->owner_id,
                'updated_at' => $reference->graph?->updated_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function neighbors(GraphNode $start, int $depth, ?string $context = null): array
    {
        $depth = max(1, min(self::MAX_DEPTH, $depth));
        $distance = [$start->id => 0];
        $frontier = [$start->id];
        $edges = [];

        for ($level = 1; $level <= $depth && $frontier !== []; $level++) {
            $next = [];
            foreach ($this->edgesTouching($frontier) as $edge) {
                $edges[$edge->source_node_id.'|'.$edge->target_node_id.'|'.$edge->relation] = $edge;
                foreach ([$edge->source_node_id, $edge->target_node_id] as $id) {
                    if (! isset($distance[$id])) {
                        $distance[$id] = $level;
                        $next[] = $id;
                    }
                }
            }
            $frontier = $next;
        }

        $nodes = $this->nodes()->whereIn('id', array_keys($distance))->get()->keyBy('id');
        if ($context !== null) {
            $nodes = $nodes->filter(static fn (GraphNode $node): bool => $node->id === $start->id || $node->context === $context);
        }

        return [
            'node' => $this->compact($start),
            'depth' => $depth,
            'neighbors' => $nodes->except($start->id)
                ->map(fn (GraphNode $node): array => $this->compact($node) + ['distance' => $distance[$node->id]])
                ->sortBy('distance')
                ->values()
                ->all(),
            'edges' => $this->edgePayload(collect($edges), $nodes),
        ];
    }

    /**
     * Shortest undirected path, or null when there is none within MAX_PATH steps.
     *
     * @return array{length:int,steps:list<array<string,mixed>>}|null
     */
    public function path(GraphNode $from, GraphNode $to): ?array
    {
        if ($from->id === $to->id) {
            return ['length' => 0, 'steps' => []];
        }

        $previous = [$from->id => null];
        $via = [];
        $frontier = [$from->id];

        for ($level = 1; $level <= self::MAX_PATH && $frontier !== [] && ! isset($previous[$to->id]); $level++) {
            $inFrontier = array_flip($frontier);
            $next = [];

            foreach ($this->edgesTouching($frontier) as $edge) {
                foreach ([[$edge->source_node_id, $edge->target_node_id], [$edge->target_node_id, $edge->source_node_id]] as [$a, $b]) {
                    if (isset($inFrontier[$a]) && ! array_key_exists($b, $previous)) {
                        $previous[$b] = $a;
                        $via[$b] = $edge;
                        $next[] = $b;
                    }
                }
            }

            $frontier = $next;
        }

        if (! array_key_exists($to->id, $previous)) {
            return null;
        }

        $chain = [];
        for ($current = $to->id; $previous[$current] !== null; $current = $previous[$current]) {
            $chain[] = $via[$current];
        }
        $chain = array_reverse($chain);

        $ids = collect($chain)->flatMap(static fn ($edge): array => [$edge->source_node_id, $edge->target_node_id])->unique();
        $nodes = $this->nodes()->whereIn('id', $ids)->get()->keyBy('id');

        return [
            'length' => count($chain),
            'steps' => $this->edgePayload(collect($chain), $nodes),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function hotspots(?string $context, ?string $kind, int $limit): array
    {
        $rows = GraphReference::query()
            ->join('graphs', 'graphs.id', '=', 'graph_references.graph_id')
            ->join('graph_nodes', 'graph_nodes.id', '=', 'graph_references.graph_node_id')
            ->where('graphs.workspace_id', $this->workspaceId)
            ->when($context !== null, fn ($query) => $query->where('graph_nodes.context', $context))
            ->when($kind !== null, fn ($query) => $query->where('graph_nodes.kind', $kind))
            ->selectRaw('graph_references.graph_node_id as node_id, COUNT(DISTINCT graph_references.graph_id) as total')
            ->groupBy('graph_references.graph_node_id')
            ->orderByDesc('total')
            ->orderBy('graph_references.graph_node_id')
            ->limit(max(1, min(100, $limit)))
            ->get();

        $nodes = $this->nodes()->whereIn('id', $rows->pluck('node_id'))->get()->keyBy('id');

        return $rows
            ->filter(static fn ($row): bool => $nodes->has($row->node_id))
            ->map(fn ($row): array => $this->compact($nodes->get($row->node_id)) + ['graphs' => (int) $row->total])
            ->values()
            ->all();
    }

    /**
     * Nodes and edges of a set of graphs, compact and keyed by canonical key.
     *
     * @param  Collection<int,int>  $graphIds
     * @return array<string,mixed>
     */
    public function subgraph(Collection $graphIds, ?string $context = null): array
    {
        $map = (new GraphView)->forMap($this->workspaceId, $graphIds, $context);
        $keys = collect($map['nodes'])->pluck('key', 'id');

        return [
            'stats' => $map['stats'],
            'nodes' => collect($map['nodes'])->map(static fn (array $node): array => [
                'key' => $node['key'],
                'kind' => $node['kind'],
                'label' => $node['label'],
                'context' => $node['context'],
                'role' => $node['role'],
                'graphs' => $node['graphs'],
            ])->all(),
            'edges' => collect($map['links'])
                ->reject(static fn (array $link): bool => $link['relation'] === 'contains')
                ->map(static fn (array $link): array => [
                    'from' => $keys->get($link['source']),
                    'to' => $keys->get($link['target']),
                    'relation' => $link['relation'],
                    'weight' => $link['weight'],
                ])
                ->values()
                ->all(),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * @return Builder<GraphNode>
     */
    private function nodes(): Builder
    {
        return GraphNode::query()->withoutGlobalScopes()->where('workspace_id', $this->workspaceId);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int,object>
     */
    private function edgesTouching(array $ids): Collection
    {
        return GraphEdge::query()
            ->join('graphs', 'graphs.id', '=', 'graph_edges.graph_id')
            ->where('graphs.workspace_id', $this->workspaceId)
            ->where(fn ($query) => $query
                ->whereIn('graph_edges.source_node_id', $ids)
                ->orWhereIn('graph_edges.target_node_id', $ids))
            ->selectRaw('graph_edges.source_node_id, graph_edges.target_node_id, graph_edges.relation, COUNT(DISTINCT graph_edges.graph_id) as weight')
            ->groupBy('graph_edges.source_node_id', 'graph_edges.target_node_id', 'graph_edges.relation')
            ->toBase()
            ->get();
    }

    /**
     * @param  Collection<int|string,object>  $edges
     * @param  Collection<int,GraphNode>  $nodes
     * @return list<array<string,mixed>>
     */
    private function edgePayload(Collection $edges, Collection $nodes): array
    {
        return $edges
            ->filter(static fn ($edge): bool => $nodes->has($edge->source_node_id) && $nodes->has($edge->target_node_id))
            ->map(static fn ($edge): array => [
                'from' => $nodes->get($edge->source_node_id)->key,
                'from_label' => GraphView::labelOf($nodes->get($edge->source_node_id)),
                'relation' => $edge->relation,
                'to' => $nodes->get($edge->target_node_id)->key,
                'to_label' => GraphView::labelOf($nodes->get($edge->target_node_id)),
                'weight' => (int) $edge->weight,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int,GraphNode>
     */
    private function descendants(GraphNode $node, int $levels): Collection
    {
        $all = collect();
        $frontier = collect([$node->id]);

        for ($level = 0; $level < $levels && $frontier->isNotEmpty(); $level++) {
            $children = $this->nodes()->whereIn('parent_node_id', $frontier)->orderBy('key')->get();
            $all = $all->merge($children);
            $frontier = $children->pluck('id');
        }

        return $all;
    }

    /**
     * @return array<string,mixed>
     */
    private function compact(GraphNode $node): array
    {
        return array_filter([
            'key' => $node->key,
            'kind' => $node->kind,
            'label' => GraphView::labelOf($node),
            'context' => $node->context,
            'role' => $node->role,
            'signature' => $node->signature,
            'visibility' => $node->visibility,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string,mixed>
     */
    private function full(GraphNode $node): array
    {
        return [
            'key' => $node->key,
            'kind' => $node->kind,
            'label' => GraphView::labelOf($node),
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
            'context' => $node->context,
            'role' => $node->role,
            'description' => $node->description,
            'purpose' => $node->purpose,
            'summary' => $node->summary,
            'meta' => $node->meta,
            'last_referenced_at' => $node->last_referenced_at?->toIso8601String(),
        ];
    }
}
