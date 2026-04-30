<?php

declare(strict_types=1);

namespace App\Modules\Views\Http\Controllers;

use App\Modules\Teams\Models\Team;
use App\Modules\Views\Enums\ViewScope;
use App\Modules\Views\Models\IssueView;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IssueViewListController
{
    public function index(Request $request): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            return Inertia::render('views/Index', [
                'views' => [],
                'teams' => [],
                'filters' => ['scope' => null, 'team' => null],
            ]);
        }

        $userId = (int) ($request->user()?->getKey() ?? 0);

        $scopeFilter = $request->query('scope');
        $scopeFilter = is_string($scopeFilter) && in_array($scopeFilter, ['personal', 'team', 'workspace'], true)
            ? $scopeFilter
            : null;

        $teamKey = $request->query('team');
        $teamKey = is_string($teamKey) && $teamKey !== '' ? strtoupper($teamKey) : null;

        $teamId = null;
        if ($teamKey !== null) {
            $teamId = Team::query()
                ->where('workspace_id', $workspace->id)
                ->where('key', $teamKey)
                ->value('id');
        }

        $query = IssueView::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($q) use ($userId): void {
                // Visibility: own personal views, any team view, any workspace view.
                $q->where(function ($qq) use ($userId): void {
                    $qq->where('scope', 'personal')->where('owner_user_id', $userId);
                })
                    ->orWhere('scope', 'team')
                    ->orWhere('scope', 'workspace');
            })
            ->with(['owner:id,name,email', 'team:id,name,key,color']);

        if ($scopeFilter !== null) {
            $query->where('scope', $scopeFilter);
        }

        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        $views = $query
            ->orderBy('scope')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'color'])
            ->map(static fn (Team $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'key' => $t->key,
                'color' => $t->color,
            ])
            ->all();

        return Inertia::render('views/Index', [
            'views' => $views->map(fn (IssueView $v): array => [
                'id' => $v->id,
                'name' => $v->name,
                'description' => $v->description,
                'scope' => $v->scope?->value,
                'team' => $v->team ? [
                    'id' => $v->team->id,
                    'name' => $v->team->name,
                    'key' => $v->team->key,
                    'color' => $v->team->color,
                ] : null,
                'filters' => $v->filters ?? [],
                'grouping' => $v->grouping,
                'sorting' => $v->sorting,
                'is_favorite' => (bool) $v->is_favorite,
                'is_owner' => (int) $v->owner_user_id === (int) ($v->owner?->id ?? 0)
                    && (int) $v->owner_user_id === (int) (auth()->id() ?? 0),
                'owner' => $v->owner ? [
                    'id' => $v->owner->id,
                    'name' => $v->owner->name,
                    'email' => $v->owner->email,
                ] : null,
                'updated_at' => $v->updated_at?->toIso8601String(),
            ])->all(),
            'teams' => $teams,
            'filters' => [
                'scope' => $scopeFilter,
                'team' => $teamKey,
            ],
            'scopes' => [
                ViewScope::Personal->value => 'Personal',
                ViewScope::Team->value => 'Team',
                ViewScope::Workspace->value => 'Workspace',
            ],
        ]);
    }
}
