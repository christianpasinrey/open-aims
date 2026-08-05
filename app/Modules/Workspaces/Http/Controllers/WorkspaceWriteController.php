<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\Support\WorkspaceProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mutations for workspaces:
 *  - PATCH /workspace/{slug}      → rename / change color
 *  - POST  /workspace/switch       → change the active workspace stored in
 *                                    the session (used by the sidebar
 *                                    workspace switcher)
 *
 * No granular permissions yet — workspace membership is enforced; only
 * `owner` / `admin` may edit the workspace itself.
 */
final class WorkspaceWriteController
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $data = $request->validate([
            'name' => 'required|string|max:60',
            'join_policy' => 'sometimes|in:open,request,private',
            'team_name' => 'sometimes|string|max:80',
            'team_key' => 'sometimes|nullable|string|max:8',
        ]);

        $provisioner = app(WorkspaceProvisioner::class);
        if ($provisioner->hasReachedLimit($user)) {
            throw ValidationException::withMessages([
                'name' => sprintf('You already own the maximum of %d workspaces.', $provisioner->limitPerUser()),
            ]);
        }

        $workspace = $provisioner->create(
            $user,
            $data['name'],
            $data['join_policy'] ?? null,
            $data['team_name'] ?? null,
            $data['team_key'] ?? null,
        );

        $request->session()->put('current_workspace_id', $workspace->id);

        return redirect()->route('issues.index');
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $workspace = Workspace::query()->where('slug', $slug)->first();
        if ($workspace === null) {
            throw new NotFoundHttpException('Workspace not found.');
        }

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->getKey())
            ->first();

        if ($member === null) {
            throw new AccessDeniedHttpException('You are not a member of this workspace.');
        }
        if (! in_array($member->role, ['owner', 'admin'], true)) {
            throw new AccessDeniedHttpException('Only owners or admins can edit the workspace.');
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:60',
            'color' => 'sometimes|nullable|string|max:9|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'telegram_enabled' => 'sometimes|boolean',
            'telegram_chat_id' => 'sometimes|nullable|string|max:64',
        ]);

        if (array_key_exists('name', $data)) {
            $workspace->name = $data['name'];
        }
        if (array_key_exists('color', $data)) {
            $color = $data['color'];
            if (is_string($color) && $color !== '' && ! str_starts_with($color, '#')) {
                $color = '#'.$color;
            }
            $settings = is_array($workspace->settings) ? $workspace->settings : [];
            if ($color === null || $color === '') {
                unset($settings['color']);
            } else {
                $settings['color'] = $color;
            }
            $workspace->settings = $settings;
        }

        if (array_key_exists('telegram_enabled', $data) || array_key_exists('telegram_chat_id', $data)) {
            $settings = is_array($workspace->settings) ? $workspace->settings : [];
            $telegram = is_array($settings['telegram'] ?? null) ? $settings['telegram'] : [];
            if (array_key_exists('telegram_enabled', $data)) {
                $telegram['enabled'] = (bool) $data['telegram_enabled'];
            }
            if (array_key_exists('telegram_chat_id', $data)) {
                $chatId = $data['telegram_chat_id'];
                $telegram['chat_id'] = (is_string($chatId) && $chatId !== '') ? $chatId : null;
            }
            $settings['telegram'] = $telegram;
            $workspace->settings = $settings;
        }

        $workspace->save();

        return back();
    }

    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $slug = (string) $request->query('workspace', '');
        if ($slug === '') {
            $slug = (string) $request->input('workspace', '');
        }
        if ($slug === '') {
            throw new NotFoundHttpException('Missing workspace slug.');
        }

        $workspace = Workspace::query()->where('slug', $slug)->first();
        if ($workspace === null) {
            throw new NotFoundHttpException('Workspace not found.');
        }

        $isMember = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->getKey())
            ->exists();
        if (! $isMember) {
            throw new AccessDeniedHttpException('You are not a member of this workspace.');
        }

        $request->session()->put('current_workspace_id', $workspace->id);

        return redirect()->intended(route('issues.index'));
    }
}
