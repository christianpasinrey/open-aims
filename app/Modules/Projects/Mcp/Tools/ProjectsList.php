<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectResource;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'List projects in the workspace. Optional filters: team key (only '
    .'projects linked to that team), status, lead ("me"|user_id|email). '
    .'Each project includes a `plan` summary (or null) so callers can tell '
    .'at a glance which projects have a plan attached.'
)]
class ProjectsList extends Tool
{
    use AttachesProjectPlan;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }
        $user = auth()->user();

        $data = Validator::make($request->all(), [
            'team' => 'nullable|string|max:16',
            'status' => 'nullable|string|in:backlog,planned,started,paused,completed,canceled',
            'lead' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ])->validate();

        $query = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with(['lead:id,name,email', 'teams:id,key,name'])
            ->withCount([
                'issues as total_issues',
                'issues as completed_issues' => fn ($q) => $q->whereHas(
                    'workflowState',
                    fn ($w) => $w->where('type', 'completed'),
                ),
            ]);

        if (! empty($data['team'])) {
            $teamId = Team::query()
                ->where('workspace_id', $workspace->id)
                ->where('key', strtoupper($data['team']))
                ->value('id');
            if ($teamId !== null) {
                $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
            }
        }

        if (! empty($data['status'])) {
            $query->where('state', $data['status']);
        }

        if (! empty($data['lead'])) {
            $leadId = $this->resolveUser($data['lead'], (int) $user?->getAuthIdentifier());
            if ($leadId !== null) {
                $query->where('lead_user_id', $leadId);
            }
        }

        $limit = (int) ($data['limit'] ?? 50);
        $projects = $query->orderBy('name')->limit($limit)->get();

        // Preload the latest plan per project to avoid N+1 lookups.
        $latestPlans = ProjectResource::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('is_plan', true)
            ->orderBy('project_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('media')
            ->get()
            ->groupBy('project_id')
            ->map(fn ($group) => $group->first());

        return Response::json([
            'count' => $projects->count(),
            'projects' => $projects->map(function (Project $p) use ($latestPlans) {
                $total = (int) $p->total_issues;
                $completed = (int) $p->completed_issues;
                $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

                return [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'state' => $p->state?->value,
                    'lead' => $p->lead?->name,
                    'teams' => $p->teams->pluck('key')->all(),
                    'total_issues' => $total,
                    'completed_issues' => $completed,
                    'progress_percent' => $percent,
                    'target_date' => $p->target_date?->toDateString(),
                    'plan' => $this->planSummary($latestPlans->get($p->id)),
                    'url' => '/projects/'.$p->slug,
                ];
            })->all(),
        ]);
    }

    private function resolveUser(string $value, int $currentUserId): ?int
    {
        if ($value === 'me') {
            return $currentUserId;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $id = User::query()->where('email', $value)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team' => $schema->string()->description('Team key — only projects linked to this team.'),
            'status' => $schema->string()->description('backlog|planned|started|paused|completed|canceled'),
            'lead' => $schema->string()->description('"me", numeric user id, or email.'),
            'limit' => $schema->integer(),
            'workspace_slug' => $schema->string(),
        ];
    }
}
