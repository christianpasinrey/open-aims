<?php

namespace App\Http\Middleware;

use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'workspace' => fn () => $this->workspacePayload(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function workspacePayload(): ?array
    {
        if (! app()->bound('current.workspace')) {
            return null;
        }

        /** @var Workspace|null $workspace */
        $workspace = app('current.workspace');
        if ($workspace === null) {
            return null;
        }

        $teams = Team::query()
            ->where('workspace_id', $workspace->getKey())
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'icon', 'color'])
            ->map(fn (Team $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'key' => $t->key,
                'icon' => $t->icon,
                'color' => $t->color,
            ])
            ->all();

        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'teams' => $teams,
        ];
    }
}
