<?php

declare(strict_types=1);

namespace App\Modules\Cycles\Http\Controllers;

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Cycles\Models\CycleResource;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CycleResourceController
{
    public function store(Request $request, int $number): RedirectResponse
    {
        $cycle = $this->resolveCycle($request, $number);

        $type = $request->input('type');
        if (! in_array($type, ['file', 'link'], true)) {
            abort(422, 'Resource type must be "file" or "link".');
        }

        if ($type === 'file') {
            $request->validate([
                'file' => 'required|file|max:25600',
                'name' => 'sometimes|string|max:200',
            ]);
            $upload = $request->file('file');
            $displayName = trim((string) $request->input('name', '')) !== ''
                ? (string) $request->input('name')
                : $upload->getClientOriginalName();

            DB::transaction(function () use ($cycle, $request, $upload, $displayName): void {
                $resource = CycleResource::create([
                    'cycle_id' => $cycle->id,
                    'type' => 'file',
                    'name' => $displayName,
                    'url' => null,
                    'created_by_user_id' => $request->user()?->id,
                ]);

                $resource->addMedia($upload->getRealPath())
                    ->usingFileName($upload->hashName())
                    ->usingName($upload->getClientOriginalName())
                    ->toMediaCollection('attachment', 'public');
            });
        } else {
            $data = $request->validate([
                'name' => 'required|string|max:200',
                'url' => 'required|url|max:1024',
            ]);

            CycleResource::create([
                'cycle_id' => $cycle->id,
                'type' => 'link',
                'name' => $data['name'],
                'url' => $data['url'],
                'created_by_user_id' => $request->user()?->id,
            ]);
        }

        return back();
    }

    public function destroy(Request $request, int $number, int $id): RedirectResponse
    {
        $cycle = $this->resolveCycle($request, $number);

        $resource = CycleResource::query()
            ->where('cycle_id', $cycle->id)
            ->where('id', $id)
            ->first();
        if ($resource === null) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $resource->delete();

        return back();
    }

    private function resolveCycle(Request $request, int $number): Cycle
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

        return $cycle;
    }

    private function teamFromRequest(Request $request, Workspace $workspace): Team
    {
        $teamKey = $request->query('team') ?? $request->input('team');
        if (! is_string($teamKey) || $teamKey === '') {
            // Fallback: workspace's first team (matches the show controller default).
            $team = Team::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('id')
                ->first();
            if ($team === null) {
                throw new NotFoundHttpException('Team not found.');
            }

            return $team;
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
}
