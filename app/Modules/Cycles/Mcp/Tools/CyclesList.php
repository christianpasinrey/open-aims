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
    'List cycles (sprints) for a team. View filter selects subset: all, current '
    .'(in-progress now), upcoming (starts in the future), completed. '
    .'Call `cycles-get` with (team_key, number) for progress totals and the issues in a cycle.'
)]
class CyclesList extends Tool
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
            'view' => 'nullable|string|in:all,current,upcoming,completed',
        ])->validate();

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($data['team_key']))
            ->first();
        if ($team === null) {
            return Response::error("Team '{$data['team_key']}' not found.");
        }

        $now = now();
        $query = Cycle::query()->where('team_id', $team->id);

        switch ($data['view'] ?? 'all') {
            case 'current':
                $query->whereNull('completed_at')
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now);
                break;
            case 'upcoming':
                $query->where('starts_at', '>', $now);
                break;
            case 'completed':
                $query->where(fn ($q) => $q->whereNotNull('completed_at')
                    ->orWhere('ends_at', '<', $now));
                break;
        }

        $cycles = $query->orderByDesc('starts_at')->get();

        return Response::json([
            'team_key' => $team->key,
            'count' => $cycles->count(),
            'cycles' => $cycles->map(function (Cycle $c) use ($now, $team) {
                $current = $c->starts_at !== null
                    && $c->ends_at !== null
                    && $c->starts_at->lte($now)
                    && $c->ends_at->gte($now);

                return [
                    'number' => $c->number,
                    'name' => $c->name,
                    'starts_at' => $c->starts_at?->toDateString(),
                    'ends_at' => $c->ends_at?->toDateString(),
                    'completed_at' => $c->completed_at?->toIso8601String(),
                    'is_current' => $current,
                    'url' => "/cycles/{$c->number}?team={$team->key}",
                ];
            })->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team_key' => $schema->string()->required(),
            'view' => $schema->string()->description('all|current|upcoming|completed'),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — when omitted '
                .'the FIRST membership by id is used, which may not be the one the user means. '
                .'Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
