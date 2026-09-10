<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Http\Controllers;

use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Support\GraphView;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * JSON for the Graphs section of an issue, project or milestone page. Pages
 * only pass the owner id; the viewer fetches the graphs from here.
 */
final class OwnerGraphsController
{
    public function show(GraphView $view, string $type, int $id): JsonResponse
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
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->with(['references.node', 'edges'])
            ->orderBy('title')
            ->get();

        return response()->json([
            'owner' => $this->describe($type, $owner),
            'graphs' => $graphs->map(fn (Graph $graph): array => [
                'id' => $graph->id,
                'title' => $graph->title,
                'summary' => $graph->summary,
                'stage' => $graph->stage,
                'version' => $graph->version,
                'repo' => $graph->repo,
                'ref' => $graph->ref,
                'updated_at' => $graph->updated_at?->toIso8601String(),
            ] + $view->forGraph($graph))->all(),
        ]);
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
