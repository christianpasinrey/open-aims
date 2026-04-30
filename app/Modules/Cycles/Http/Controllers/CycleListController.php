<?php

declare(strict_types=1);

namespace App\Modules\Cycles\Http\Controllers;

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CycleListController
{
    public function index(Request $request): Response
    {
        $teamKey = $request->query('team');
        $teamKey = is_string($teamKey) && $teamKey !== '' ? strtoupper($teamKey) : null;

        $view = $request->query('view');
        $view = is_string($view) ? strtolower($view) : 'all';
        if (! in_array($view, ['all', 'current', 'upcoming', 'completed'], true)) {
            $view = 'all';
        }

        $sort = $request->query('sort');
        $sort = is_string($sort) ? strtolower($sort) : 'date_desc';
        if (! in_array($sort, ['date_desc', 'number_desc'], true)) {
            $sort = 'date_desc';
        }

        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            return $this->emptyResponse($view, $sort, $teamKey);
        }

        $teamQuery = Team::query()->where('workspace_id', $workspace->id);
        $team = $teamKey !== null
            ? $teamQuery->where('key', $teamKey)->first()
            : $teamQuery->orderBy('name')->first();

        if ($team === null) {
            return $this->emptyResponse($view, $sort, $teamKey);
        }

        $cyclesQuery = Cycle::query()->where('team_id', $team->id);

        $now = now();

        switch ($view) {
            case 'current':
                $cyclesQuery
                    ->whereNull('completed_at')
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now);
                break;
            case 'upcoming':
                $cyclesQuery->where('starts_at', '>', $now);
                break;
            case 'completed':
                $cyclesQuery->where(function ($q) use ($now): void {
                    $q->whereNotNull('completed_at')
                        ->orWhere('ends_at', '<', $now);
                });
                break;
            case 'all':
            default:
                // no filter
                break;
        }

        $cyclesQuery = $sort === 'number_desc'
            ? $cyclesQuery->orderByDesc('number')
            : $cyclesQuery->orderByDesc('starts_at');

        $cycles = $cyclesQuery->get();

        return Inertia::render('cycles/Index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ],
            'cycles' => $cycles->map(function (Cycle $c) use ($now): array {
                $isCurrent = $c->starts_at !== null
                    && $c->ends_at !== null
                    && $c->starts_at->lte($now)
                    && $c->ends_at->gte($now);

                return [
                    'id' => $c->id,
                    'number' => $c->number,
                    'name' => $c->name,
                    'description' => $c->description,
                    'starts_at' => $c->starts_at?->toDateString(),
                    'ends_at' => $c->ends_at?->toDateString(),
                    'completed_at' => $c->completed_at?->toIso8601String(),
                    'is_current' => $isCurrent,
                ];
            })->all(),
            'filters' => [
                'team' => $team->key,
                'view' => $view,
                'sort' => $sort,
            ],
        ]);
    }

    private function emptyResponse(string $view, string $sort, ?string $teamKey): Response
    {
        return Inertia::render('cycles/Index', [
            'cycles' => [],
            'team' => null,
            'filters' => [
                'team' => $teamKey,
                'view' => $view,
                'sort' => $sort,
            ],
        ]);
    }
}
