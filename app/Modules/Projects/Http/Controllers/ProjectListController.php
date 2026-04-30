<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Models\User;
use App\Modules\Projects\Enums\ProjectState;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
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
                'members' => [],
                'filters' => $this->emptyFilters(),
            ]);
        }

        $teamKey = $request->query('team');
        $teamKey = is_string($teamKey) && $teamKey !== '' ? strtoupper($teamKey) : null;

        $statusFilter = $this->normaliseStatus($request->query('status'));
        $leadFilter = $this->normaliseInt($request->query('lead'));
        $group = $this->normaliseEnum($request->query('group'), ['none', 'status', 'lead'], 'none');
        $sort = $this->normaliseEnum(
            $request->query('sort'),
            ['status', 'name', 'target', 'issues'],
            'status',
        );

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

        if ($statusFilter !== null) {
            $projectsQuery->where('state', $statusFilter);
        }

        if ($leadFilter !== null) {
            $projectsQuery->where('lead_user_id', $leadFilter);
        }

        // Apply ordering. The frontend handles grouping; we just need the
        // rows to come back in the right inner order so each group reads
        // correctly. For "status" sort we mirror the previous default
        // (started → planned → paused → backlog → completed → canceled).
        switch ($sort) {
            case 'name':
                $projectsQuery->orderBy('name');
                break;
            case 'target':
                $projectsQuery->orderByRaw('target_date IS NULL')->orderBy('target_date')->orderBy('name');
                break;
            case 'issues':
                $projectsQuery->orderByDesc('total_issues')->orderBy('name');
                break;
            case 'status':
            default:
                $projectsQuery->orderByRaw("CASE state
                    WHEN 'started' THEN 0
                    WHEN 'planned' THEN 1
                    WHEN 'paused' THEN 2
                    WHEN 'backlog' THEN 3
                    WHEN 'completed' THEN 4
                    WHEN 'canceled' THEN 5
                    ELSE 6 END")
                    ->orderBy('name');
                break;
        }

        $projects = $projectsQuery->get();

        // Workspace members — used by the New project dialog (lead picker)
        // and by the Filter dropdown (lead submenu).
        $memberIds = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('user_id');

        $members = User::query()
            ->whereIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])
            ->all();

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
            'members' => $members,
            'filters' => [
                'team' => $teamKey,
                'status' => $statusFilter,
                'lead' => $leadFilter,
                'group' => $group,
                'sort' => $sort,
            ],
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

    /**
     * @return array<string,mixed>
     */
    private function emptyFilters(): array
    {
        return [
            'team' => null,
            'status' => null,
            'lead' => null,
            'group' => 'none',
            'sort' => 'status',
        ];
    }

    private function normaliseStatus(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $value = strtolower($value);
        $allowed = array_map(static fn (ProjectState $s): string => $s->value, ProjectState::cases());

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function normaliseInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && $value !== '') {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  array<int,string>  $allowed
     */
    private function normaliseEnum(mixed $value, array $allowed, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
