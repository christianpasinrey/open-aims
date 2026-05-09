<?php

declare(strict_types=1);

namespace App\Modules\Teams\Http\Controllers;

use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mutations on a team's general settings (name, color, icon).
 * The team key is treated as immutable once issued because it appears in
 * issue identifiers (e.g. ENG-123).
 */
final class TeamWriteController
{
    public function update(Request $request, string $key): RedirectResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($key))
            ->first();
        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:80',
            'color' => 'sometimes|nullable|string|max:9|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'icon' => 'sometimes|nullable|string|max:32',
            'description' => 'sometimes|nullable|string|max:500',
            'github_repo_full_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:200',
                // owner/repo format (single slash, no spaces, no leading slash)
                'regex:/^[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+$/',
            ],
        ]);

        if (array_key_exists('github_repo_full_name', $data)
            && is_string($data['github_repo_full_name'])
            && $data['github_repo_full_name'] === ''
        ) {
            $data['github_repo_full_name'] = null;
        }

        if (array_key_exists('color', $data) && is_string($data['color']) && $data['color'] !== '' && ! str_starts_with($data['color'], '#')) {
            $data['color'] = '#'.$data['color'];
        }

        $team->fill($data)->save();

        return back();
    }
}
