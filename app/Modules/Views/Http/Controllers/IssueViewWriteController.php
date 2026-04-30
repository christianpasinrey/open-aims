<?php

declare(strict_types=1);

namespace App\Modules\Views\Http\Controllers;

use App\Modules\Teams\Models\Team;
use App\Modules\Views\Models\IssueView;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IssueViewWriteController
{
    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace();
        $userId = (int) ($request->user()?->getKey() ?? 0);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'scope' => 'required|in:personal,team,workspace',
            'team_key' => 'nullable|string|max:16',
            'filters' => 'nullable|array',
            'grouping' => 'nullable|string|max:32',
            'sorting' => 'nullable|string|max:32',
        ]);

        $teamId = null;
        if (($data['scope'] ?? null) === 'team' && ! empty($data['team_key'])) {
            $teamId = Team::query()
                ->where('workspace_id', $workspace->id)
                ->where('key', strtoupper((string) $data['team_key']))
                ->value('id');
        }

        $view = IssueView::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'owner_user_id' => $userId,
            'scope' => $data['scope'],
            'team_id' => $teamId,
            'filters' => $data['filters'] ?? [],
            'grouping' => $data['grouping'] ?? 'status',
            'sorting' => $data['sorting'] ?? 'priority',
            'is_favorite' => false,
        ]);

        return redirect()->route('views.show', ['view' => $view->id]);
    }

    public function update(Request $request, int $view): RedirectResponse
    {
        $issueView = $this->resolve($view, $request, mustOwn: true);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|nullable|string',
            'scope' => 'sometimes|in:personal,team,workspace',
            'team_key' => 'sometimes|nullable|string|max:16',
            'filters' => 'sometimes|nullable|array',
            'grouping' => 'sometimes|string|max:32',
            'sorting' => 'sometimes|string|max:32',
        ]);

        if (array_key_exists('team_key', $data)) {
            $teamKey = $data['team_key'];
            unset($data['team_key']);
            if ($teamKey !== null && $teamKey !== '') {
                $data['team_id'] = Team::query()
                    ->where('workspace_id', $issueView->workspace_id)
                    ->where('key', strtoupper((string) $teamKey))
                    ->value('id');
            } else {
                $data['team_id'] = null;
            }
        }

        $issueView->fill($data)->save();

        return back();
    }

    public function destroy(Request $request, int $view): RedirectResponse
    {
        $issueView = $this->resolve($view, $request, mustOwn: true);
        $issueView->delete();

        return redirect()->route('views.index');
    }

    public function favorite(Request $request, int $view): RedirectResponse
    {
        // Anyone who can see the view can star it for themselves. For now we
        // store a single is_favorite flag on the row (per-user favourites
        // would need a join table; we keep parity with the spec).
        $issueView = $this->resolve($view, $request, mustOwn: false);
        $issueView->is_favorite = ! $issueView->is_favorite;
        $issueView->save();

        return back();
    }

    private function resolve(int $id, Request $request, bool $mustOwn): IssueView
    {
        $workspace = $this->workspace();
        $userId = (int) ($request->user()?->getKey() ?? 0);

        $view = IssueView::query()
            ->where('workspace_id', $workspace->id)
            ->where('id', $id)
            ->first();
        if ($view === null) {
            throw new NotFoundHttpException('View not found.');
        }

        if ($mustOwn && (int) $view->owner_user_id !== $userId) {
            // Hide existence — same response as not found.
            throw new NotFoundHttpException('View not found.');
        }

        // Personal scope is private to its owner.
        if ($view->scope?->value === 'personal' && (int) $view->owner_user_id !== $userId) {
            throw new NotFoundHttpException('View not found.');
        }

        return $view;
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
