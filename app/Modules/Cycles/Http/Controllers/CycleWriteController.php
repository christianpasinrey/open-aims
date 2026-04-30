<?php

declare(strict_types=1);

namespace App\Modules\Cycles\Http\Controllers;

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mutations on cycles from the repo-style UI: create + partial update.
 *
 * Workspace membership is enforced upstream by ResolveWorkspace + auth.
 * Both endpoints resolve the team via the `?team=KEY` query string because
 * cycle numbers are not globally unique — they are scoped per team.
 */
final class CycleWriteController
{
    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace();
        $team = $this->teamFromRequest($request, $workspace);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'number' => 'nullable|integer|min:1',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'description' => 'nullable|string',
        ]);

        $cycle = DB::transaction(function () use ($team, $data): Cycle {
            $number = $data['number'] ?? null;
            if ($number === null || $number === 0) {
                $max = (int) Cycle::query()
                    ->where('team_id', $team->id)
                    ->max('number');
                $number = $max + 1;
            }

            $name = $data['name'] ?? null;
            if ($name === null || $name === '') {
                $name = 'Cycle '.$number;
            }

            return Cycle::create([
                'team_id' => $team->id,
                'name' => $name,
                'number' => $number,
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
            ]);
        });

        return redirect()->route('cycles.show', [
            'number' => $cycle->number,
            'team' => $team->key,
        ]);
    }

    public function update(Request $request, int $number): RedirectResponse
    {
        $workspace = $this->workspace();
        $team = $this->teamFromRequest($request, $workspace);

        $cycle = Cycle::query()
            ->where('team_id', $team->id)
            ->where('number', $number)
            ->first();
        if ($cycle === null) {
            throw new NotFoundHttpException('Cycle not found.');
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'starts_at' => 'sometimes|required|date',
            'ends_at' => 'sometimes|required|date',
            'completed_at' => 'sometimes|nullable|date',
        ]);

        $cycle->fill($data)->save();

        return back();
    }

    private function workspace(): Workspace
    {
        if (! app()->bound('current.workspace')) {
            abort(404, 'No active workspace.');
        }
        $w = app('current.workspace');
        if (! $w instanceof Workspace) {
            abort(404, 'No active workspace.');
        }

        return $w;
    }

    private function teamFromRequest(Request $request, Workspace $workspace): Team
    {
        $teamKey = $request->query('team');
        if (! is_string($teamKey) || $teamKey === '') {
            throw new NotFoundHttpException('Team is required.');
        }

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($teamKey))
            ->first();
        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        return $team;
    }
}
