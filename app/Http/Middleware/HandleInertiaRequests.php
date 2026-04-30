<?php

namespace App\Http\Middleware;

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Favourites\Models\UserFavourite;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
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
            'user_workspaces' => fn () => $this->userWorkspacesPayload($request),
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

        $now = now();

        $teams = Team::query()
            ->where('workspace_id', $workspace->getKey())
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'icon', 'color'])
            ->map(function (Team $t) use ($now): array {
                $current = Cycle::query()
                    ->where('team_id', $t->id)
                    ->whereNull('completed_at')
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now)
                    ->orderBy('starts_at')
                    ->value('number');

                $upcoming = Cycle::query()
                    ->where('team_id', $t->id)
                    ->whereNull('completed_at')
                    ->where('starts_at', '>', $now)
                    ->orderBy('starts_at')
                    ->value('number');

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'key' => $t->key,
                    'icon' => $t->icon,
                    'color' => $t->color,
                    'current_cycle_number' => $current !== null ? (int) $current : null,
                    'upcoming_cycle_number' => $upcoming !== null ? (int) $upcoming : null,
                ];
            })
            ->all();

        $userId = (int) (auth()->id() ?? 0);
        $favourites = [];
        if ($userId > 0) {
            $favourites = UserFavourite::query()
                ->where('user_id', $userId)
                ->where('workspace_id', $workspace->getKey())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(50)
                ->get()
                ->map(fn (UserFavourite $f): array => [
                    'id' => (int) $f->id,
                    'kind' => (string) $f->kind,
                    'label' => (string) $f->label,
                    'icon' => $f->icon,
                    'color' => $f->color,
                    'href' => (string) $f->href,
                    'target_id' => $f->target_id !== null ? (int) $f->target_id : null,
                    'target_type' => $f->target_type,
                ])
                ->all();
        }

        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'color' => self::workspaceColor($workspace),
            'logo_url' => $workspace->logo_url,
            'teams' => $teams,
            'favourites' => $favourites,
        ];
    }

    /**
     * The workspaces the current user is a member of, used by the sidebar
     * switcher. Returns null when there's no auth user.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function userWorkspacesPayload(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $rows = WorkspaceMember::query()
            ->where('user_id', $user->getKey())
            ->with('workspace:id,name,slug,settings,logo_url')
            ->get(['id', 'workspace_id', 'role'])
            ->filter(fn (WorkspaceMember $m) => $m->workspace !== null);

        return $rows->map(function (WorkspaceMember $m): array {
            /** @var Workspace $w */
            $w = $m->workspace;

            return [
                'id' => $w->id,
                'name' => $w->name,
                'slug' => $w->slug,
                'color' => self::workspaceColor($w),
                'logo_url' => $w->logo_url,
                'role' => $m->role,
            ];
        })->values()->all();
    }

    /**
     * Workspaces don't have a dedicated color column yet; we read an
     * optional `color` from `settings` JSON, falling back to a default
     * repo-purple.
     */
    public static function workspaceColor(Workspace $workspace): string
    {
        $settings = $workspace->settings;
        if (is_array($settings) && isset($settings['color']) && is_string($settings['color']) && $settings['color'] !== '') {
            return $settings['color'];
        }

        return '#6366f1';
    }
}
