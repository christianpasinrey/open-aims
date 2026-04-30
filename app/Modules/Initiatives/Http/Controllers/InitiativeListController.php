<?php

declare(strict_types=1);

namespace App\Modules\Initiatives\Http\Controllers;

use App\Models\User;
use App\Modules\Initiatives\Enums\InitiativeState;
use App\Modules\Initiatives\Models\Initiative;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InitiativeListController
{
    public function index(Request $request): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            return Inertia::render('initiatives/Index', [
                'initiatives' => [],
                'states' => $this->stateOptions(),
                'members' => [],
                'filters' => $this->emptyFilters(),
            ]);
        }

        $stateFilter = $this->normaliseState($request->query('state'));
        $ownerFilter = $this->normaliseInt($request->query('owner'));

        $query = Initiative::query()
            ->where('workspace_id', $workspace->id)
            ->with([
                'owner:id,name,email',
                'projects' => function ($q): void {
                    $q->select('projects.id', 'projects.name', 'projects.slug', 'projects.color', 'projects.icon', 'projects.state')
                        ->withCount([
                            'issues as total_issues',
                            'issues as completed_issues' => static fn ($qq) => $qq->whereHas(
                                'workflowState',
                                static fn ($w) => $w->where('type', 'completed'),
                            ),
                        ]);
                },
            ]);

        if ($stateFilter !== null) {
            $query->where('state', $stateFilter);
        }

        if ($ownerFilter !== null) {
            $query->where('owner_user_id', $ownerFilter);
        }

        $initiatives = $query
            ->orderByRaw("CASE state
                WHEN 'active' THEN 0
                WHEN 'planned' THEN 1
                WHEN 'completed' THEN 2
                WHEN 'canceled' THEN 3
                ELSE 4 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

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

        return Inertia::render('initiatives/Index', [
            'initiatives' => $initiatives->map(function (Initiative $i): array {
                $projects = $i->projects;
                $projectsCount = $projects->count();
                $totalIssues = 0;
                $completedIssues = 0;
                $progressSum = 0;
                $progressWeight = 0;
                foreach ($projects as $p) {
                    $total = (int) ($p->total_issues ?? 0);
                    $completed = (int) ($p->completed_issues ?? 0);
                    $totalIssues += $total;
                    $completedIssues += $completed;
                    $percent = $total > 0 ? ($completed / $total) * 100 : 0;
                    $progressSum += $percent * max($total, 1);
                    $progressWeight += max($total, 1);
                }
                $completion = $progressWeight > 0
                    ? (int) round($progressSum / $progressWeight)
                    : 0;

                return [
                    'id' => $i->id,
                    'name' => $i->name,
                    'slug' => $i->slug,
                    'description' => $i->description ? mb_substr((string) $i->description, 0, 200) : null,
                    'state' => $i->state?->value,
                    'color' => $i->color,
                    'icon' => $i->icon,
                    'start_date' => $i->start_date?->toDateString(),
                    'target_date' => $i->target_date?->toDateString(),
                    'completed_at' => $i->completed_at?->toIso8601String(),
                    'owner' => $i->owner ? [
                        'id' => $i->owner->id,
                        'name' => $i->owner->name,
                        'email' => $i->owner->email,
                    ] : null,
                    'parent_initiative_id' => $i->parent_initiative_id,
                    'projects_count' => $projectsCount,
                    'total_issues' => $totalIssues,
                    'completed_issues' => $completedIssues,
                    'completion_percent' => $completion,
                ];
            })->all(),
            'states' => $this->stateOptions(),
            'members' => $members,
            'filters' => [
                'state' => $stateFilter,
                'owner' => $ownerFilter,
            ],
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function stateOptions(): array
    {
        $out = [];
        foreach (InitiativeState::cases() as $case) {
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
            'state' => null,
            'owner' => null,
        ];
    }

    private function normaliseState(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $value = strtolower($value);
        $allowed = array_map(static fn (InitiativeState $s): string => $s->value, InitiativeState::cases());

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
}
