<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Http\Controllers;

use App\Modules\Graphs\Exceptions\GraphScopeNotFound;
use App\Modules\Graphs\Support\GraphScope;
use App\Modules\Graphs\Support\GraphView;
use App\Modules\Graphs\Support\GraphVocabulary;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * JSON for the workspace map: every graph merged, optionally narrowed to
 * what a project or milestone contributed (including its issues) and to
 * one context.
 */
final class GraphMapController
{
    public function show(Request $request, GraphView $view): JsonResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $filters = $request->validate([
            'project' => ['nullable', 'string', 'max:200'],
            'milestone' => ['nullable', 'integer', 'min:1'],
            'context' => ['nullable', Rule::in(GraphVocabulary::CONTEXTS)],
        ]);

        try {
            $graphIds = GraphScope::graphIds(
                (int) $workspace->id,
                $filters['project'] ?? null,
                isset($filters['milestone']) ? (int) $filters['milestone'] : null,
            );
        } catch (GraphScopeNotFound $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }

        return response()->json($view->forMap((int) $workspace->id, $graphIds, $filters['context'] ?? null));
    }
}
