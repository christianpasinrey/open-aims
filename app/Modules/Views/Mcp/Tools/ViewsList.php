<?php

declare(strict_types=1);

namespace App\Modules\Views\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Views\Models\IssueView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'List saved issue views (repo-style saved searches). Includes the '
    .'authenticated user\'s personal views, team-scoped views for teams '
    .'they belong to, and workspace-wide views.'
)]
class ViewsList extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }
        $userId = (int) (auth()->user()?->getAuthIdentifier() ?? 0);

        $views = IssueView::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($q) use ($userId) {
                $q->where('scope', 'workspace')
                    ->orWhere('scope', 'team')
                    ->orWhere(fn ($qq) => $qq->where('scope', 'personal')
                        ->where('owner_user_id', $userId));
            })
            ->with('owner:id,name,email')
            ->orderBy('scope')
            ->orderBy('name')
            ->get();

        return Response::json([
            'count' => $views->count(),
            'views' => $views->map(fn (IssueView $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'description' => $v->description,
                'scope' => $v->scope?->value ?? null,
                'is_favorite' => (bool) $v->is_favorite,
                'owner' => $v->owner?->name,
                'url' => '/views/'.$v->id,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_slug' => $schema->string(),
        ];
    }
}
