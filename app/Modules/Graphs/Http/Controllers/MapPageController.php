<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Http\Controllers;

use App\Modules\Graphs\Support\GraphVocabulary;
use App\Modules\Projects\Models\Project;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The workspace map page. It only receives the filters and the project
 * picker options; the graph itself is fetched from /graphs/map.
 */
final class MapPageController
{
    public function show(Request $request): Response
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

        return Inertia::render('graphs/Map', [
            'filters' => [
                'project' => $filters['project'] ?? null,
                'milestone' => isset($filters['milestone']) ? (int) $filters['milestone'] : null,
                'context' => $filters['context'] ?? null,
            ],
            'projects' => Project::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'color', 'icon'])
                ->map(static fn (Project $project): array => [
                    'slug' => $project->slug,
                    'name' => $project->name,
                    'color' => $project->color,
                    'icon' => $project->icon,
                ])
                ->all(),
            'contexts' => GraphVocabulary::CONTEXTS,
        ]);
    }
}
