<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Return the active workspace, the teams it contains (key + name + icon), '
    .'and the member count. Call this first to discover available team keys.'
)]
class WorkspaceCurrent extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace for this user.');
        }

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'icon', 'color', 'description']);

        $memberCount = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->count();

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
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_slug' => $schema->string()
                ->description('Optional workspace slug if the user belongs to several. Defaults to first.'),
        ];
    }
}
