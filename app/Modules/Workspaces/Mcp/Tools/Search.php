<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Full-text search across issues, projects, and cycles in the active '
    .'workspace. Returns up to `limit` matches sorted by relevance proxy '
    .'(recency).'
)]
class WorkspaceSearch extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }

        $data = Validator::make($request->all(), [
            'query' => 'required|string|min:1|max:200',
            'limit' => 'nullable|integer|min:1|max:50',
        ])->validate();

        $q = $data['query'];
        $limit = (int) ($data['limit'] ?? 10);
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('key', 'id');

        $issues = Issue::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->where(fn ($q) => $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'team_id', 'number', 'title']);

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('description', 'like', $like))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'name', 'slug']);

        $cycles = Cycle::query()
            ->whereIn('team_id', $teams->keys())
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('description', 'like', $like))
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get(['id', 'team_id', 'number', 'name']);

        $results = [];
        foreach ($issues as $i) {
            $key = $teams[$i->team_id] ?? '?';
            $results[] = [
                'kind' => 'issue',
                'identifier' => "{$key}-{$i->number}",
                'title' => $i->title,
                'url' => "/issues/{$key}-{$i->number}",
            ];
        }
        foreach ($projects as $p) {
            $results[] = [
                'kind' => 'project',
                'identifier' => $p->slug,
                'title' => $p->name,
                'url' => "/projects/{$p->slug}",
            ];
        }
        foreach ($cycles as $c) {
            $key = $teams[$c->team_id] ?? '?';
            $results[] = [
                'kind' => 'cycle',
                'identifier' => "{$key}#{$c->number}",
                'title' => $c->name ?? "Cycle {$c->number}",
                'url' => "/cycles/{$c->number}?team={$key}",
            ];
        }

        return Response::json(['results' => array_slice($results, 0, $limit)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()
                ->description('Search term (matched against title and description).'),
            'limit' => $schema->integer()
                ->description('Maximum results (1..50, default 10).'),
            'workspace_slug' => $schema->string()
                ->description('Optional workspace override.'),
        ];
    }
}
