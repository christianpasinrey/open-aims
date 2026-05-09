<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Core\Scopes\WorkspaceScope;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Archive an issue (sets archived_at = now). Pass `unarchive: true` to '
    .'clear archived_at instead.'
)]
class IssuesArchive extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }

        $data = Validator::make($request->all(), [
            'identifier' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
            'unarchive' => 'sometimes|boolean',
        ])->validate();

        [$key, $number] = explode('-', strtoupper($data['identifier']));
        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', $key)
            ->first();
        if ($team === null) {
            return Response::error("Team '{$key}' not found.");
        }

        $issue = Issue::withoutGlobalScope(WorkspaceScope::class)
            ->where('team_id', $team->id)
            ->where('number', (int) $number)
            ->first();
        if ($issue === null) {
            return Response::error("Issue {$data['identifier']} not found.");
        }

        $issue->forceFill([
            'archived_at' => ! empty($data['unarchive']) ? null : now(),
        ])->save();

        return Response::json([
            'identifier' => $team->key.'-'.$issue->number,
            'archived' => $issue->archived_at !== null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required(),
            'unarchive' => $schema->boolean()->description('If true, clears archived_at instead of setting it.'),
            'workspace_slug' => $schema->string(),
        ];
    }
}
