<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Workspaces\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists soft-deleted resources (currently only projects) so the user can
 * restore them or delete permanently. Workspace-scoped.
 */
final class TrashController
{
    public function index(): Response
    {
        $workspace = $this->workspace();

        $projects = Project::query()
            ->onlyTrashed()
            ->where('workspace_id', $workspace->id)
            ->with(['lead:id,name,email'])
            ->orderByDesc('deleted_at')
            ->get();

        $rows = $projects->map(function (Project $p): array {
            $deletedAt = $p->deleted_at;
            $issueCount = Issue::query()
                ->withoutGlobalScopes()
                ->where('project_id', $p->id)
                ->whereNotNull('deleted_at')
                ->count();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'color' => $p->color,
                'icon' => $p->icon,
                'state' => $p->state?->value,
                'deleted_at' => $deletedAt?->toIso8601String(),
                'issues_count' => $issueCount,
                'lead' => $p->lead ? [
                    'id' => $p->lead->id,
                    'name' => $p->lead->name,
                    'email' => $p->lead->email,
                ] : null,
            ];
        })->all();

        return Inertia::render('trash/Index', [
            'projects' => $rows,
        ]);
    }

    private function workspace(): Workspace
    {
        if (! app()->bound('current.workspace')) {
            abort(404, 'No active workspace.');
        }
        $w = app('current.workspace');
        if (! $w instanceof Workspace) {
            abort(404, 'No active workspace.');
        }

        return $w;
    }
}
