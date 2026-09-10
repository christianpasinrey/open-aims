<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Http\Controllers;

use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Support\GraphOwnerDescriber;
use App\Modules\Graphs\Support\GraphScope;
use App\Modules\Graphs\Support\GraphView;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * JSON for the Graphs section of an issue, project or milestone page. Pages
 * only pass the owner id; the viewer lists the graphs from here and fetches
 * the nodes of the selected one from GraphShowController.
 *
 * A project also lists the graphs of its milestones and issues, and a
 * milestone those of its issues, so work documented on issues shows up there.
 */
final class OwnerGraphsController
{
    /** Graphs of the requested owner come first, then by owner type. */
    private const OWNER_ORDER = ['project' => 0, 'milestone' => 1, 'issue' => 2];

    public function show(string $type, int $id): JsonResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $owner = $this->owner($workspace, $type, $id);
        if ($owner === null) {
            throw new NotFoundHttpException('Owner not found.');
        }

        $graphs = Graph::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $this->graphIds($workspace, $type, $owner))
            ->get();

        return response()->json([
            'owner' => $this->describe($type, $owner),
            'graphs' => $this->listing($graphs, $owner),
        ]);
    }

    /**
     * @return Collection<int,int>
     */
    private function graphIds(Workspace $workspace, string $type, Model $owner): Collection
    {
        return match ($type) {
            'project' => GraphScope::graphIds((int) $workspace->id, (string) $owner->slug),
            'milestone' => GraphScope::graphIds((int) $workspace->id, null, (int) $owner->getKey()),
            default => Graph::query()
                ->where('workspace_id', $workspace->id)
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->pluck('id'),
        };
    }

    /**
     * @param  Collection<int,Graph>  $graphs
     * @return list<array<string,mixed>>
     */
    private function listing(Collection $graphs, Model $owner): array
    {
        $owners = [];

        return $graphs
            ->map(function (Graph $graph) use ($owner, &$owners): array {
                $key = $graph->owner_type.'|'.$graph->owner_id;
                $owners[$key] ??= GraphOwnerDescriber::describe((string) $graph->owner_type, (int) $graph->owner_id);

                return GraphView::summaryOf($graph) + [
                    'own' => $graph->owner_type === $owner->getMorphClass()
                        && (int) $graph->owner_id === (int) $owner->getKey(),
                    'owner' => $owners[$key],
                ];
            })
            ->sortBy([
                static fn (array $a, array $b): int => $b['own'] <=> $a['own'],
                static fn (array $a, array $b): int => (self::OWNER_ORDER[$a['owner']['type'] ?? ''] ?? 9)
                    <=> (self::OWNER_ORDER[$b['owner']['type'] ?? ''] ?? 9),
                static fn (array $a, array $b): int => strnatcmp($a['owner']['identifier'] ?? '', $b['owner']['identifier'] ?? ''),
                static fn (array $a, array $b): int => strcmp($a['title'], $b['title']),
            ])
            ->values()
            ->all();
    }

    private function owner(Workspace $workspace, string $type, int $id): ?Model
    {
        return match ($type) {
            'issue' => Issue::query()
                ->where('workspace_id', $workspace->id)
                ->with('team:id,key')
                ->find($id),
            'project' => Project::query()
                ->where('workspace_id', $workspace->id)
                ->find($id),
            'milestone' => ProjectMilestone::query()
                ->whereKey($id)
                ->whereHas('project', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->with('project:id,slug,name')
                ->first(),
            default => null,
        };
    }

    /**
     * @return array{type:string,id:int,name:string,url:string}
     */
    private function describe(string $type, Model $owner): array
    {
        return match ($type) {
            'issue' => [
                'type' => 'issue',
                'id' => (int) $owner->getKey(),
                'name' => ($owner->team?->key ?? '?').'-'.$owner->number.' '.$owner->title,
                'url' => '/issues/'.($owner->team?->key ?? '?').'-'.$owner->number,
            ],
            'project' => [
                'type' => 'project',
                'id' => (int) $owner->getKey(),
                'name' => (string) $owner->name,
                'url' => '/projects/'.$owner->slug,
            ],
            default => [
                'type' => 'milestone',
                'id' => (int) $owner->getKey(),
                'name' => (string) $owner->name,
                'url' => '/projects/'.$owner->project?->slug.'/milestones/'.$owner->getKey(),
            ],
        };
    }
}
