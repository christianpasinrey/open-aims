<?php

declare(strict_types=1);

namespace App\Modules\Initiatives\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Initiatives\Models\Initiative;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'List initiatives in the workspace. Optional state filter (planned, '
    .'active, completed, canceled).'
)]
class InitiativesList extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }

        $data = Validator::make($request->all(), [
            'state' => 'nullable|string|in:planned,active,completed,canceled',
            'limit' => 'nullable|integer|min:1|max:100',
        ])->validate();

        $query = Initiative::query()
            ->where('workspace_id', $workspace->id)
            ->with(['owner:id,name,email'])
            ->withCount('projects');

        if (! empty($data['state'])) {
            $query->where('state', $data['state']);
        }

        $items = $query->orderBy('name')->limit((int) ($data['limit'] ?? 50))->get();

        return Response::json([
            'count' => $items->count(),
            'initiatives' => $items->map(fn (Initiative $i) => [
                'slug' => $i->slug,
                'name' => $i->name,
                'state' => $i->state?->value,
                'owner' => $i->owner?->name,
                'project_count' => (int) $i->projects_count,
                'target_date' => $i->target_date?->toDateString(),
                'url' => '/initiatives/'.$i->slug,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'state' => $schema->string()->description('planned|active|completed|canceled'),
            'limit' => $schema->integer(),
            'workspace_slug' => $schema->string(),
        ];
    }
}
