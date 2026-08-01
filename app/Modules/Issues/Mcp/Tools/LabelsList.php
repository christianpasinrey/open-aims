<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'List every label defined on a team, with its colour and description. '
    .'Labels are per-team, never global. Call this BEFORE passing `labels` to '
    .'issues-create / issues-update: those tools only attach labels that already '
    .'exist and report the rest in `labels_unknown`. Use `labels-ensure` to create '
    .'missing ones.'
)]
class LabelsList extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $data = Validator::make($request->all(), [
            'team_key' => 'required|string|max:16',
        ])->validate();

        $team = Team::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('key', strtoupper($data['team_key']))
            ->first();
        if ($team === null) {
            return Response::error("Team '{$data['team_key']}' not found in workspace '{$workspace->slug}'.");
        }

        $labels = Label::query()
            ->where('team_id', $team->getKey())
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'description']);

        return Response::json([
            'team_key' => $team->key,
            'count' => $labels->count(),
            'labels' => $labels->map(fn (Label $label): array => [
                'name' => $label->name,
                'color' => $label->color,
                'description' => $label->description,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team_key' => $schema->string()->required()->description('Team key (e.g. "LAM").'),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — '
                .'when omitted the FIRST membership by id is used, which may not be the one '
                .'the user means. Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
