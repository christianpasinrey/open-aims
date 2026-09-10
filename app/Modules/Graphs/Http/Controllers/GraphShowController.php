<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Http\Controllers;

use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Support\GraphView;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** One graph with its nodes and links, for the tab selected in a Graphs section. */
final class GraphShowController
{
    public function show(GraphView $view, int $graph): JsonResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $model = Graph::query()
            ->where('workspace_id', $workspace->id)
            ->with(['references.node', 'edges'])
            ->find($graph);
        if ($model === null) {
            throw new NotFoundHttpException('Graph not found.');
        }

        return response()->json(GraphView::summaryOf($model) + $view->forGraph($model));
    }
}
