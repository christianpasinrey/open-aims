<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Modules\Projects\Enums\ProjectState;
use App\Modules\Projects\Models\Project;
use App\Modules\Workspaces\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectListController
{
    public function index(): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            return Inertia::render('projects/Index', ['projects' => [], 'states' => $this->stateOptions()]);
        }

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with(['lead:id,name,email', 'members.user:id,name,email'])
            ->withCount(['issues as total_issues'])
            ->orderByRaw("CASE state
                WHEN 'started' THEN 0
                WHEN 'planned' THEN 1
                WHEN 'paused' THEN 2
                WHEN 'backlog' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'canceled' THEN 5
                ELSE 6 END")
            ->orderBy('name')
            ->get();

        return Inertia::render('projects/Index', [
            'projects' => $projects->map(fn (Project $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'state' => $p->state?->value,
                'color' => $p->color,
                'icon' => $p->icon,
                'description' => $p->description ? mb_substr((string) $p->description, 0, 200) : null,
                'start_date' => $p->start_date?->toDateString(),
                'target_date' => $p->target_date?->toDateString(),
                'completed_at' => $p->completed_at?->toIso8601String(),
                'lead' => $p->lead ? [
                    'id' => $p->lead->id,
                    'name' => $p->lead->name,
                    'email' => $p->lead->email,
                ] : null,
                'members' => $p->members->map(fn ($m): array => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                ])->filter(static fn ($m) => $m['id'] !== null)->values()->all(),
                'total_issues' => (int) $p->total_issues,
            ])->all(),
            'states' => $this->stateOptions(),
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function stateOptions(): array
    {
        $out = [];
        foreach (ProjectState::cases() as $case) {
            $out[$case->value] = ucfirst($case->value);
        }

        return $out;
    }
}
