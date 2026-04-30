<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Modules\Projects\Enums\ProjectState;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectListController
{
    public function index(Request $request): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            return Inertia::render('projects/Index', [
                'projects' => [],
                'states' => $this->stateOptions(),
                'team' => null,
            ]);
        }

        $teamKey = $request->query('team');
        $teamKey = is_string($teamKey) && $teamKey !== '' ? strtoupper($teamKey) : null;

        $team = null;
        if ($teamKey !== null) {
            $team = Team::query()
                ->where('workspace_id', $workspace->id)
                ->where('key', $teamKey)
                ->first();
        }

        $projectsQuery = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with(['lead:id,name,email', 'members.user:id,name,email'])
            ->withCount([
                'issues as total_issues',
                'issues as completed_issues' => static fn ($q) => $q->whereHas(
                    'workflowState',
                    static fn ($w) => $w->where('type', 'completed'),
                ),
            ]);

        if ($team !== null) {
            $projectsQuery->whereHas(
                'teams',
                static fn ($q) => $q->where('teams.id', $team->id),
            );
        }

        $projects = $projectsQuery
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
            'team' => $team !== null ? [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ] : null,
            'projects' => $projects->map(function (Project $p): array {
                $total = (int) $p->total_issues;
                $completed = (int) $p->completed_issues;
                $progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

                return [
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
                        'email' => $m->user?->email,
                    ])->filter(static fn ($m) => $m['id'] !== null)->values()->all(),
                    'total_issues' => $total,
                    'completed_issues' => $completed,
                    'progress' => $progress,
                ];
            })->all(),
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
