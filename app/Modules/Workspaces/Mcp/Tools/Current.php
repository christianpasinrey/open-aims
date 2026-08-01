<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Orientation tool — call this FIRST. Returns the active workspace (id, name, '
    .'slug), the teams it contains (key + name + icon), its member count, and '
    .'`available_workspaces`: EVERY workspace the authenticated user can reach, '
    .'each with {slug, name, is_active}. When `available_workspaces` holds more '
    .'than one entry the caller MUST pass an explicit `workspace_slug` on every '
    .'later tool call, because omitting it falls back to the first membership by '
    .'id, which may not be the workspace the user means.'
)]
class Current extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'icon', 'color', 'description']);

        $memberCount = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->count();

        // Every workspace the caller can reach, so a client never assumes the
        // active one is the only one. `is_active` marks the workspace this call
        // resolved to; the rest need an explicit workspace_slug to reach.
        $available = Workspace::query()
            ->whereIn('id', WorkspaceMember::query()
                ->where('user_id', auth()->user()?->getAuthIdentifier())
                ->select('workspace_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Workspace $w): array => [
                'slug' => $w->slug,
                'name' => $w->name,
                'is_active' => (int) $w->id === (int) $workspace->id,
            ])->all();

        return Response::json([
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'member_count' => $memberCount,
            'teams' => $teams->map(fn (Team $t) => [
                'key' => $t->key,
                'name' => $t->name,
                'icon' => $t->icon,
                'color' => $t->color,
                'description' => $t->description,
            ])->all(),
            'available_workspaces' => $available,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_slug' => $schema->string()
                ->description('Workspace slug. Omit only when the user belongs to a single workspace — when omitted the FIRST membership by id is used, which may not be the one the user means. Get valid slugs from the `current` tool.'),
        ];
    }
}
