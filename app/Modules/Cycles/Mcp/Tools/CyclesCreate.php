<?php

declare(strict_types=1);

namespace App\Modules\Cycles\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Create a new cycle for a team. A CYCLE IS A SPRINT: a fixed time window '
    .'(starts_at → ends_at) that a team commits a set of issues to. '
    .'Typical use: create the cycle for the sprint window, then attach issues to it '
    .'with `issues-create` or `issues-update` passing `cycle_number` (plus the same '
    .'`team_key`) — cycles are addressed as (team_key, number), never by id. '
    .'Use `cycles-get` for sprint review and planning: it returns progress totals, '
    .'per-assignee breakdown and the issues in the cycle. '
    .'Auto-numbers within the team if no number is supplied. '
    .'Defaults the name to "Cycle N" if missing.'
)]
class CyclesCreate extends Tool
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
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'name' => 'nullable|string|max:200',
            'number' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
        ])->validate();

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($data['team_key']))
            ->first();
        if ($team === null) {
            return Response::error("Team '{$data['team_key']}' not found.");
        }

        $number = (int) ($data['number']
            ?? ((int) Cycle::query()->where('team_id', $team->id)->max('number') + 1));

        $cycle = Cycle::create([
            'team_id' => $team->id,
            'number' => $number,
            'name' => $data['name'] ?? "Cycle {$number}",
            'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
        ]);

        return Response::json([
            'team_key' => $team->key,
            'number' => $cycle->number,
            'name' => $cycle->name,
            'url' => "/cycles/{$cycle->number}?team={$team->key}",
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team_key' => $schema->string()->required()->description('Team the sprint belongs to, e.g. "ENG".'),
            'starts_at' => $schema->string()->required()->description('YYYY-MM-DD. First day of the sprint window.'),
            'ends_at' => $schema->string()->required()->description('YYYY-MM-DD. Last day of the sprint window.'),
            'name' => $schema->string()->description('Defaults to "Cycle N".'),
            'number' => $schema->integer()->description(
                'Sprint number within the team. Auto-incremented if omitted. This is the '
                .'`cycle_number` you pass to `issues-create` / `issues-update` to attach issues.'
            ),
            'description' => $schema->string()->description('Sprint goal / commitment for this window.'),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — when omitted '
                .'the FIRST membership by id is used, which may not be the one the user means. '
                .'Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
