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
    'Idempotently create labels on a team. Pass the label names you need and any '
    .'that are missing are created; the ones already there are left untouched '
    .'(matching is case-insensitive, so "Bug" will not be duplicated as "bug"). '
    .'Returns `created` and `existing`. Run this before issues-create / issues-update '
    .'when you are not sure the labels exist — those tools never create labels and '
    .'would report unknown names in `labels_unknown` instead.'
)]
class LabelsEnsure extends Tool
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
            'labels' => 'required|array|min:1',
            'labels.*' => 'string|max:64',
            'color' => 'nullable|string|max:9|regex:/^#[0-9A-Fa-f]{3,8}$/',
        ])->validate();

        $team = Team::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('key', strtoupper($data['team_key']))
            ->first();
        if ($team === null) {
            return Response::error("Team '{$data['team_key']}' not found in workspace '{$workspace->slug}'.");
        }

        /** @var array<string,string> $existingByLower */
        $existingByLower = Label::query()
            ->where('team_id', $team->getKey())
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [mb_strtolower($name) => $name])
            ->all();

        $created = [];
        $existing = [];

        foreach ($data['labels'] as $raw) {
            $name = trim((string) $raw);
            if ($name === '') {
                continue;
            }

            $lower = mb_strtolower($name);

            if (isset($existingByLower[$lower])) {
                $canonical = $existingByLower[$lower];
                if (! in_array($canonical, $existing, true)) {
                    $existing[] = $canonical;
                }

                continue;
            }

            $label = Label::create(array_filter([
                'team_id' => $team->getKey(),
                'name' => $name,
                'color' => $data['color'] ?? null,
            ], static fn ($value): bool => $value !== null));

            $existingByLower[$lower] = $label->name;
            $created[] = $label->name;
        }

        return Response::json([
            'team_key' => $team->key,
            'created' => $created,
            'existing' => $existing,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team_key' => $schema->string()->required()->description('Team key (e.g. "LAM").'),
            'labels' => $schema->array()->items($schema->string())->required()->description(
                'Label names to guarantee exist on the team. Matching is case-insensitive.'
            ),
            'color' => $schema->string()->description(
                'Hex colour (e.g. "#22c55e") applied to labels created by this call. '
                .'Existing labels keep their colour. Defaults to the team label colour.'
            ),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — '
                .'when omitted the FIRST membership by id is used, which may not be the one '
                .'the user means. Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
