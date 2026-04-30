<?php

declare(strict_types=1);

namespace App\Modules\Favourites\Http\Controllers;

use App\Modules\Favourites\Models\UserFavourite;
use App\Modules\Views\Models\IssueView;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FavouriteController
{
    /**
     * POST /favourites/toggle
     *
     * If a row matching (user_id, workspace_id, kind, target_type, target_id)
     * already exists, it is deleted (off). Otherwise a new row is created
     * (on). Returns {starred: bool} via Inertia/JSON.
     */
    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $workspace = $this->workspace();

        $data = $request->validate([
            'kind' => 'required|string|in:view,issue,project,cycle,team_view,inbox,page',
            'target_type' => 'nullable|string|max:255',
            'target_id' => 'nullable|integer',
            'href' => 'required|string|max:500',
            'label' => 'required|string|max:200',
            'icon' => 'nullable|string|max:64',
            'color' => 'nullable|string|max:32',
        ]);

        $kind = (string) $data['kind'];
        $targetType = $data['target_type'] ?? null;
        $targetId = isset($data['target_id']) ? (int) $data['target_id'] : null;
        $href = (string) $data['href'];

        // Locate existing row. For pages (no target_id), match on href so the
        // unique key still scopes correctly.
        $query = UserFavourite::query()
            ->where('user_id', $user->getKey())
            ->where('workspace_id', $workspace->getKey())
            ->where('kind', $kind);

        if ($targetId !== null) {
            $query->where('target_type', $targetType)->where('target_id', $targetId);
        } else {
            $query->whereNull('target_id')->where('href', $href);
        }

        /** @var UserFavourite|null $existing */
        $existing = $query->first();

        if ($existing !== null) {
            $existing->delete();

            // Mirror to IssueView.is_favorite for backwards compat.
            if ($kind === 'view' && $targetId !== null) {
                IssueView::query()->where('id', $targetId)->update(['is_favorite' => false]);
            }

            return $this->respond($request, false);
        }

        UserFavourite::create([
            'user_id' => $user->getKey(),
            'workspace_id' => $workspace->getKey(),
            'kind' => $kind,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'label' => $data['label'],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'href' => $href,
            'sort_order' => 0,
        ]);

        if ($kind === 'view' && $targetId !== null) {
            IssueView::query()->where('id', $targetId)->update(['is_favorite' => true]);
        }

        return $this->respond($request, true);
    }

    /**
     * PATCH /favourites/{id} — currently only supports sort_order updates
     * (drag-to-reorder, future).
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $fav = $this->resolve($request, $id);

        $data = $request->validate([
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $fav->fill($data)->save();

        return back();
    }

    /**
     * DELETE /favourites/{id} — explicit unstar.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $fav = $this->resolve($request, $id);

        if ($fav->kind === 'view' && $fav->target_id !== null) {
            IssueView::query()->where('id', $fav->target_id)->update(['is_favorite' => false]);
        }

        $fav->delete();

        return back();
    }

    private function resolve(Request $request, int $id): UserFavourite
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $workspace = $this->workspace();

        $fav = UserFavourite::query()
            ->where('id', $id)
            ->where('user_id', $user->getKey())
            ->where('workspace_id', $workspace->getKey())
            ->first();

        if ($fav === null) {
            throw new NotFoundHttpException('Favourite not found.');
        }

        return $fav;
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

    private function respond(Request $request, bool $starred): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['starred' => $starred]);
        }

        return back()->with('favourite_starred', $starred);
    }
}
