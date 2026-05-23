<?php

declare(strict_types=1);

namespace App\Modules\Teams\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the teams in the active workspace (key, name, color, and a live count of issues in each team).')]
class TeamsList extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->withCount('issues')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'color']);

        return Response::json([
            'data' => $teams->map(fn (Team $t): array => [
                'key' => $t->key,
                'name' => $t->name,
                'color' => $t->color,
                'issue_count' => (int) $t->issues_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
