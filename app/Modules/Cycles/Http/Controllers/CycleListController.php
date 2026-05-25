<?php

declare(strict_types=1);

namespace App\Modules\Cycles\Http\Controllers;

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            return $this->emptyResponse($teamKey);
        }

        $teamQuery = Team::query()->where('workspace_id', $workspace->id);
        $team = $teamKey !== null
            ? $teamQuery->where('key', $teamKey)->first()
            : $teamQuery->orderBy('name')->first();

        if ($team === null) {
            return $this->emptyResponse($teamKey);
        }

        $now = CarbonImmutable::now();

        // Pre-load workflow state types so we can classify issues.
        /** @var array<int,string> $stateTypeById */
        $stateTypeById = WorkflowState::query()
            ->where('team_id', $team->id)
            ->pluck('type', 'id')
            ->all();

        // Order chronologically so we can render the timeline top-down with
        // future cycles first (repo shows the future at the top, the
        // current/past at the bottom).
        $cycles = Cycle::query()
            ->where('team_id', $team->id)
            ->orderByDesc('starts_at')
            ->get();

        // Pull every issue tied to any of these cycles in one query.
        $cycleIds = $cycles->pluck('id')->all();
        $issues = Issue::query()
            ->whereIn('cycle_id', $cycleIds)
            ->get(['id', 'cycle_id', 'workflow_state_id', 'created_at', 'started_at', 'completed_at', 'canceled_at']);

        $issuesByCycle = $issues->groupBy('cycle_id');

        $cyclesPayload = $cycles->map(function (Cycle $c) use ($issuesByCycle, $stateTypeById, $now): array {
            /** @var Collection<int,Issue> $bucket */
            $bucket = $issuesByCycle->get($c->id, collect());

            $scope = $bucket->count();
            $completed = $bucket->filter(static function (Issue $i) use ($stateTypeById): bool {
                $type = $stateTypeById[$i->workflow_state_id] ?? null;

                return $type === 'completed' || $i->completed_at !== null;
            })->count();
            $started = $bucket->filter(static function (Issue $i) use ($stateTypeById): bool {
                $type = $stateTypeById[$i->workflow_state_id] ?? null;

                return $type === 'started' && $i->completed_at === null && $i->canceled_at === null;
            })->count();

            $isCurrent = $c->starts_at !== null
                && $c->ends_at !== null
                && $c->starts_at->lte($now)
                && $c->ends_at->gte($now)
                && $c->completed_at === null;

            $isCompleted = $c->completed_at !== null
                || ($c->ends_at !== null && $c->ends_at->lt($now));

            $state = $isCurrent ? 'current' : ($isCompleted ? 'completed' : 'planned');
            // repo distinguishes "Upcoming" (planned but starts soon) — anything
            // that hasn't started yet is Upcoming if it's the first not-current.
            // We just emit "planned" / "upcoming" / "current" / "completed" and
            // the frontend chooses the badge.
            if (! $isCurrent && ! $isCompleted && $c->starts_at !== null && $c->starts_at->gt($now)) {
                $state = 'upcoming';
            }

            // Capacity is just scope ÷ team velocity. Since we don't track
            // velocity yet we expose raw scope; the UI also renders
            // "X% of capacity" using a per-cycle nominal of 30 (heuristic),
            // matching repo's visual until a real velocity is computed.
            $nominalCapacity = 30;
            $ofCapacity = $nominalCapacity > 0 ? (int) round($scope / $nominalCapacity * 100) : 0;

            $payload = [
                'id' => $c->id,
                'number' => $c->number,
                'name' => $c->name,
                'description' => $c->description,
                'starts_at' => $c->starts_at?->toDateString(),
                'ends_at' => $c->ends_at?->toDateString(),
                'completed_at' => $c->completed_at?->toIso8601String(),
                'state' => $state,
                'scope' => $scope,
                'started' => $started,
                'completed' => $completed,
                'of_capacity' => $ofCapacity,
                'burndown' => null,
            ];

            if ($isCurrent && $c->starts_at !== null && $c->ends_at !== null) {
                $payload['burndown'] = $this->burndownSeries($bucket, $c->starts_at->toImmutable(), $c->ends_at->toImmutable(), $stateTypeById, $now);
            }

            return $payload;
        })->all();

        // Optional server-side filtering by lifecycle state and sorting.
        // Without these params the full, chronologically-ordered list is
        // returned (the default the timeline UI relies on).
        $view = $request->query('view');
        if (in_array($view, ['current', 'upcoming', 'completed'], true)) {
            $cyclesPayload = array_values(array_filter(
                $cyclesPayload,
                static fn (array $c): bool => $c['state'] === $view,
            ));
        }

        $sort = $request->query('sort');
        if ($sort === 'number_desc') {
            usort($cyclesPayload, static fn (array $a, array $b): int => $b['number'] <=> $a['number']);
        }

        return Inertia::render('cycles/Index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ],
            'cycles' => $cyclesPayload,
            'filters' => [
                'team' => $team->key,
                'view' => is_string($view) ? $view : 'all',
                'sort' => is_string($sort) ? $sort : null,
            ],
        ]);
    }

    /**
     * Build a daily burndown series for a single cycle.
     *
     * @param  Collection<int,Issue>  $bucket
     * @param  array<int,string>  $stateTypeById
     * @return array{points:list<array{date:string,scope:int,started:int,completed:int}>,ideal:list<array{date:string,value:float}>}
     */
    private function burndownSeries(
        $bucket,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $stateTypeById,
        CarbonImmutable $now,
    ): array {
        $period = CarbonPeriod::between($start->startOfDay(), $end->endOfDay())->days(1);
        $points = [];
        $upTo = $now->lt($end) ? $now : $end;

        foreach ($period as $day) {
            $cursor = CarbonImmutable::parse($day)->endOfDay();
            $afterToday = $cursor->gt($upTo);

            $scope = 0;
            $started = 0;
            $completed = 0;

            foreach ($bucket as $issue) {
                $createdAt = $issue->created_at;
                if ($createdAt === null || $createdAt->gt($cursor)) {
                    continue;
                }
                $scope++;

                if ($issue->completed_at !== null && $issue->completed_at->lte($cursor)) {
                    $completed++;
                } elseif ($issue->started_at !== null && $issue->started_at->lte($cursor)) {
                    $started++;
                } else {
                    $type = $stateTypeById[$issue->workflow_state_id] ?? null;
                    if ($type === 'completed') {
                        $completed++;
                    } elseif ($type === 'started') {
                        $started++;
                    }
                }
            }

            $points[] = [
                'date' => $cursor->toDateString(),
                'scope' => $scope,
                'started' => $started,
                'completed' => $completed,
                'projected' => $afterToday,
            ];
        }

        // Ideal line: scope_at_start linearly down to 0 over the cycle length.
        $finalScope = $points !== [] ? max(array_column($points, 'scope')) : 0;
        $totalDays = max(1, count($points) - 1);
        $ideal = [];
        foreach ($points as $idx => $p) {
            $ideal[] = [
                'date' => $p['date'],
                'value' => max(0.0, $finalScope - ($finalScope * ($idx / $totalDays))),
            ];
        }

        return ['points' => $points, 'ideal' => $ideal, 'finalScope' => $finalScope];
    }

    private function emptyResponse(?string $teamKey): Response
    {
        return Inertia::render('cycles/Index', [
            'cycles' => [],
            'team' => null,
            'filters' => ['team' => $teamKey],
        ]);
    }
}
