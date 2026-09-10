<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Http\Controllers;

use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Support\GraphImpact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** JSON impact of a graph, with node ids so the viewer can highlight them. */
final class GraphImpactController
{
    public function show(Request $request, GraphImpact $impact, int $graph): JsonResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $depth = $request->validate([
            'depth' => ['nullable', 'integer', 'min:1', 'max:'.GraphImpact::MAX_DEPTH],
        ])['depth'] ?? 2;

        $model = Graph::query()
            ->where('workspace_id', $workspace->id)
            ->find($graph);
        if ($model === null) {
            throw new NotFoundHttpException('Graph not found.');
        }

        return response()->json($impact->analyse($model, (int) $depth));
    }
}
