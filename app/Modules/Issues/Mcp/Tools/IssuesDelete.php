<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Permanently delete an issue. Requires `confirm: true` to prevent '
    .'accidents — without it the call returns an error.'
)]
class IssuesDelete extends Tool
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
            'confirm' => 'required|boolean|in:1,true',
        ])->validate();

        if ($data['confirm'] !== true && $data['confirm'] !== 1) {
            return Response::error('confirm must be true to delete.');
        }

        [$key, $number] = explode('-', strtoupper($data['identifier']));
        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', $key)
            ->first();
        if ($team === null) {
            return Response::error("Team '{$key}' not found.");
        }

        $issue = Issue::withoutGlobalScope(\App\Core\Scopes\WorkspaceScope::class)
            ->where('team_id', $team->id)
            ->where('number', (int) $number)
            ->first();
        if ($issue === null) {
            return Response::error("Issue {$data['identifier']} not found.");
        }

        $issue->delete();

        return Response::json([
            'identifier' => $data['identifier'],
            'deleted' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required(),
            'confirm' => $schema->boolean()->required()->description('Must be true to actually delete.'),
            'workspace_slug' => $schema->string(),
        ];
    }
}
