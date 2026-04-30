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

        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            return Inertia::render('cycles/Index', ['cycles' => [], 'team' => null]);
        }

        $teamQuery = Team::query()->where('workspace_id', $workspace->id);
        $team = $teamKey !== null
            ? $teamQuery->where('key', $teamKey)->first()
            : $teamQuery->orderBy('name')->first();

        if ($team === null) {
            return Inertia::render('cycles/Index', ['cycles' => [], 'team' => null]);
        }

        $cycles = Cycle::query()
            ->where('team_id', $team->id)
            ->orderByDesc('starts_at')
            ->get();

        $now = now();

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
        ]);
    }
}
