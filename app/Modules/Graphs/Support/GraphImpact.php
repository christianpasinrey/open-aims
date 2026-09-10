<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Support;

use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Models\GraphEdge;
use App\Modules\Graphs\Models\GraphNode;
use App\Modules\Graphs\Models\GraphReference;
use Illuminate\Support\Collection;

/**
 * What a graph would affect in the workspace map.
 *
 * Seeds are the nodes the graph marks as modified or removed. From them the
 * walk goes backwards through dependencies (whoever calls, reads, listens to,
 * renders, extends or tests a seed) and forwards through hand-offs (data a
 * seed writes, events it dispatches), so readers of a column the change
 * writes are affected too. Edges of the analysed graph itself are ignored.
 */
final readonly class GraphImpact
{
    public const MAX_DEPTH = 3;

    /** `source RELATION node`: source depends on node. */
    private const DEPENDS = ['calls', 'reads', 'listens', 'renders', 'uses', 'extends', 'implements', 'routes_to', 'tests'];

    /** `node RELATION target`: the change travels on to whoever depends on target. */
    private const HANDS_OFF = ['writes', 'dispatches'];

    private const SEED_CHANGES = ['modified', 'removed'];

    /**
     * @return array<string,mixed>
     */
    public function analyse(Graph $graph, int $depth = 2): array
    {
        $depth = max(1, min(self::MAX_DEPTH, $depth));
        $workspaceId = (int) $graph->workspace_id;

        $seedReferences = GraphReference::query()
            ->where('graph_id', $graph->id)
            ->whereIn('change', self::SEED_CHANGES)
            ->with('node')
            ->get()
            ->filter(static fn (GraphReference $reference): bool => $reference->node !== null);
        $seedIds = $seedReferences->pluck('graph_node_id')->unique()->values();

        [$distance, $via] = $this->walk($workspaceId, (int) $graph->id, $seedIds->all(), $depth);

        $nodes = GraphNode::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', array_keys($distance))
            ->get()
            ->keyBy('id');

        $affected = $nodes
            ->reject(static fn (GraphNode $node): bool => $distance[$node->id] === 0)
            ->map(fn (GraphNode $node): array => $this->affected($node, $distance[$node->id], $via[$node->id], $nodes))
            ->sortBy([['distance', 'asc'], ['key', 'asc']])
            ->values();

        return [
            'graph' => ['id' => $graph->id, 'title' => $graph->title, 'stage' => $graph->stage],
            'depth' => $depth,
            'seeds' => $seedReferences
                ->unique('graph_node_id')
                ->map(static fn (GraphReference $reference): array => [
                    'id' => $reference->node->id,
                    'key' => $reference->node->key,
                    'label' => GraphView::labelOf($reference->node),
                    'kind' => $reference->node->kind,
                    'change' => $reference->change,
                    'visibility' => $reference->node->visibility,
                ])
                ->values()
                ->all(),
            'affected' => $affected->all(),
            'tests_to_run' => $affected
                ->filter(static fn (array $node): bool => $node['context'] === 'tests' || $node['role'] === 'test')
                ->map(static fn (array $node): array => ['key' => $node['key'], 'label' => $node['label'], 'file' => $node['file']])
                ->values()
                ->all(),
            'collisions' => $this->collisions($workspaceId, $graph, $seedReferences),
            'signature_changes' => $this->signatureChanges($graph, $seedReferences),
            'summary' => [
                'seeds' => $seedIds->count(),
                'public_seeds' => $seedReferences
                    ->filter(static fn (GraphReference $reference): bool => in_array($reference->node->visibility, ['public', 'exported'], true))
                    ->unique('graph_node_id')
                    ->count(),
                'affected' => $affected->count(),
                'by_context' => $affected->pluck('context')->filter()->countBy()->all(),
                'by_role' => $affected->pluck('role')->filter()->countBy()->all(),
            ],
        ];
    }

    /**
     * @param  list<int>  $seedIds
     * @return array{0: array<int,int>, 1: array<int,array{relation:string,from:int}>}
     */
    private function walk(int $workspaceId, int $graphId, array $seedIds, int $depth): array
    {
        $distance = array_fill_keys($seedIds, 0);
        $via = [];
        $frontier = $seedIds;

        for ($level = 1; $level <= $depth && $frontier !== []; $level++) {
            $next = [];

            foreach ($this->edges($workspaceId, $graphId, $frontier, 'target_node_id', self::DEPENDS) as $edge) {
                $this->reach((int) $edge->source_node_id, (int) $edge->target_node_id, $edge->relation, $level, $distance, $via, $next);
            }

            foreach ($this->edges($workspaceId, $graphId, $frontier, 'source_node_id', self::HANDS_OFF) as $edge) {
                $this->reach((int) $edge->target_node_id, (int) $edge->source_node_id, $edge->relation, $level, $distance, $via, $next);
            }

            $frontier = $next;
        }

        return [$distance, $via];
    }

    /**
     * @param  array<int,int>  $distance
     * @param  array<int,array{relation:string,from:int}>  $via
     * @param  list<int>  $next
     */
    private function reach(int $node, int $from, string $relation, int $level, array &$distance, array &$via, array &$next): void
    {
        if (array_key_exists($node, $distance)) {
            return;
        }

        $distance[$node] = $level;
        $via[$node] = ['relation' => $relation, 'from' => $from];
        $next[] = $node;
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $relations
     * @return Collection<int,object>
     */
    private function edges(int $workspaceId, int $graphId, array $ids, string $column, array $relations): Collection
    {
        return GraphEdge::query()
            ->join('graphs', 'graphs.id', '=', 'graph_edges.graph_id')
            ->where('graphs.workspace_id', $workspaceId)
            ->where('graph_edges.graph_id', '!=', $graphId)
            ->whereIn('graph_edges.'.$column, $ids)
            ->whereIn('graph_edges.relation', $relations)
            ->select(['graph_edges.source_node_id', 'graph_edges.target_node_id', 'graph_edges.relation'])
            ->distinct()
            ->toBase()
            ->get();
    }

    /**
     * @param  array{relation:string,from:int}  $via
     * @param  Collection<int,GraphNode>  $nodes
     * @return array<string,mixed>
     */
    private function affected(GraphNode $node, int $distance, array $via, Collection $nodes): array
    {
        $from = $nodes->get($via['from']);

        return [
            'id' => $node->id,
            'key' => $node->key,
            'label' => GraphView::labelOf($node),
            'kind' => $node->kind,
            'file' => $node->file_path,
            'context' => $node->context,
            'role' => $node->role,
            'signature' => $node->signature,
            'visibility' => $node->visibility,
            'distance' => $distance,
            'via' => [
                'relation' => $via['relation'],
                'from' => $from?->key,
                'from_label' => $from !== null ? GraphView::labelOf($from) : null,
            ],
        ];
    }

    /**
     * Other graphs of open work that reference the same seeds.
     *
     * @param  Collection<int,GraphReference>  $seedReferences
     * @return list<array<string,mixed>>
     */
    private function collisions(int $workspaceId, Graph $graph, Collection $seedReferences): array
    {
        $seedLabels = $seedReferences
            ->mapWithKeys(static fn (GraphReference $reference): array => [
                $reference->graph_node_id => GraphView::labelOf($reference->node),
            ]);

        if ($seedLabels->isEmpty()) {
            return [];
        }

        return GraphReference::query()
            ->join('graphs', 'graphs.id', '=', 'graph_references.graph_id')
            ->where('graphs.workspace_id', $workspaceId)
            ->where('graphs.id', '!=', $graph->id)
            ->whereIn('graph_references.graph_node_id', $seedLabels->keys())
            ->get([
                'graph_references.graph_id',
                'graph_references.graph_node_id',
                'graph_references.change',
                'graphs.title',
                'graphs.stage',
                'graphs.owner_type',
                'graphs.owner_id',
            ])
            ->groupBy('graph_id')
            ->filter(static fn (Collection $rows): bool => GraphOwnerDescriber::isOpen(
                (string) $rows->first()->owner_type,
                (int) $rows->first()->owner_id,
            ))
            ->map(static fn (Collection $rows): array => [
                'graph_id' => (int) $rows->first()->graph_id,
                'graph' => $rows->first()->title,
                'stage' => $rows->first()->stage,
                'owner' => GraphOwnerDescriber::describe((string) $rows->first()->owner_type, (int) $rows->first()->owner_id),
                'shared' => $rows->map(static fn ($row): array => [
                    'label' => $seedLabels->get($row->graph_node_id),
                    'their_change' => $row->change,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Functions whose signature in this graph differs from the latest one
     * another graph recorded.
     *
     * @param  Collection<int,GraphReference>  $seedReferences
     * @return list<array<string,mixed>>
     */
    private function signatureChanges(Graph $graph, Collection $seedReferences): array
    {
        return $seedReferences
            ->filter(static fn (GraphReference $reference): bool => $reference->node->kind === 'function'
                && isset($reference->meta['signature']))
            ->map(static function (GraphReference $reference) use ($graph): ?array {
                $previous = GraphReference::query()
                    ->where('graph_node_id', $reference->graph_node_id)
                    ->where('graph_id', '!=', $graph->id)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->get(['meta'])
                    ->map(static fn (GraphReference $other): ?string => $other->meta['signature'] ?? null)
                    ->filter()
                    ->first();

                if ($previous === null || $previous === $reference->meta['signature']) {
                    return null;
                }

                return [
                    'key' => $reference->node->key,
                    'label' => GraphView::labelOf($reference->node),
                    'visibility' => $reference->node->visibility,
                    'from' => $previous,
                    'to' => $reference->meta['signature'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
