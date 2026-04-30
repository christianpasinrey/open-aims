<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectPreviewController
{
    public function show(string $slug): JsonResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->with('lead:id,name,email')
            ->first();

        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        $totals = Issue::query()
            ->where('project_id', $project->id)
            ->selectRaw('COUNT(*) as total, SUM(completed_at IS NOT NULL) as completed')
            ->first();
        $total = (int) ($totals->total ?? 0);
        $completed = (int) ($totals->completed ?? 0);
        $progress = $total === 0 ? 0.0 : round($completed / $total, 2);

        return response()->json([
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $this->summary($project->description, 280),
            'state' => $project->state,
            'color' => $project->color,
            'icon' => $project->icon,
            'start_date' => $project->start_date?->toDateString(),
            'target_date' => $project->target_date?->toDateString(),
            'completed_at' => $project->completed_at?->toIso8601String(),
            'lead' => $project->lead ? [
                'id' => $project->lead->id,
                'name' => $project->lead->name,
                'email' => $project->lead->email,
            ] : null,
            'issues' => [
                'total' => $total,
                'completed' => $completed,
                'progress' => $progress,
            ],
        ]);
    }

    private function summary(?string $body, int $max): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        if (mb_strlen($plain) <= $max) {
            return $plain;
        }

        return mb_substr($plain, 0, $max).'…';
    }
}
