<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Workspaces\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectDetailController
{
    public function show(string $slug): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->with([
                'lead:id,name,email',
                'members.user:id,name,email',
                'milestones',
                'teams:id,name,key,color',
            ])
            ->first();

        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        $issues = Issue::query()
            ->where('project_id', $project->id)
            ->whereNull('archived_at')
            ->with([
                'team:id,key,name,color',
                'workflowState:id,name,type,color,position',
                'assignee:id,name,email',
                'labels:id,name,color',
            ])
            ->orderByRaw('CASE WHEN priority = 0 THEN 5 ELSE priority END')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'state' => $project->state?->value,
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
                'members' => $project->members->map(fn ($m): array => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                    'role' => $m->role,
                ])->filter(static fn ($m) => $m['id'] !== null)->values()->all(),
                'milestones' => $project->milestones->map(fn ($ms): array => [
                    'id' => $ms->id,
                    'name' => $ms->name,
                    'description' => $ms->description,
                    'target_date' => $ms->target_date,
                ])->all(),
                'teams' => $project->teams->map(fn ($t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'key' => $t->key,
                    'color' => $t->color,
                ])->all(),
            ],
            'issues' => $issues->map(fn (Issue $i): array => [
                'id' => $i->id,
                'identifier' => ($i->team?->key ?? '?').'-'.$i->number,
                'title' => $i->title,
                'priority' => (int) ($i->priority?->value ?? 0),
                'state' => $i->workflowState ? [
                    'name' => $i->workflowState->name,
                    'type' => $i->workflowState->type,
                    'color' => $i->workflowState->color,
                ] : null,
                'assignee' => $i->assignee ? [
                    'id' => $i->assignee->id,
                    'name' => $i->assignee->name,
                ] : null,
                'labels' => $i->labels->map(fn ($l): array => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                ])->all(),
                'updated_at' => $i->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
