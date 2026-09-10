<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Support;

use App\Modules\Graphs\Exceptions\GraphScopeNotFound;
use App\Modules\Graphs\Models\Graph;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which graphs belong to the map being asked for: the whole workspace, or
 * what a project or milestone contributed — including their issues.
 * Shared by the web map and the graphs-map MCP tool.
 */
final class GraphScope
{
    /**
     * @return Collection<int,int> graph ids
     *
     * @throws GraphScopeNotFound
     */
    public static function graphIds(int $workspaceId, ?string $projectSlug = null, ?int $milestoneId = null): Collection
    {
        $graphs = Graph::query()->withoutGlobalScopes()->where('workspace_id', $workspaceId);

        if ($projectSlug !== null && $projectSlug !== '') {
            self::narrowToProject($graphs, $workspaceId, $projectSlug);
        }

        if ($milestoneId !== null) {
            self::narrowToMilestone($graphs, $workspaceId, $milestoneId);
        }

        return $graphs->pluck('id');
    }

    /**
     * @param  Builder<Graph>  $graphs
     */
    private static function narrowToProject(Builder $graphs, int $workspaceId, string $slug): void
    {
        $project = Project::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();
        if ($project === null) {
            throw new GraphScopeNotFound("Project '{$slug}' not found.");
        }

        $milestoneIds = ProjectMilestone::query()->where('project_id', $project->id)->pluck('id');
        $issueIds = Issue::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $project->id)
            ->pluck('id');

        $graphs->where(function (Builder $query) use ($project, $milestoneIds, $issueIds): void {
            $query->where(fn (Builder $q) => $q
                ->where('owner_type', $project->getMorphClass())
                ->where('owner_id', $project->id))
                ->orWhere(fn (Builder $q) => $q
                    ->where('owner_type', (new ProjectMilestone)->getMorphClass())
                    ->whereIn('owner_id', $milestoneIds))
                ->orWhere(fn (Builder $q) => $q
                    ->where('owner_type', (new Issue)->getMorphClass())
                    ->whereIn('owner_id', $issueIds));
        });
    }

    /**
     * @param  Builder<Graph>  $graphs
     */
    private static function narrowToMilestone(Builder $graphs, int $workspaceId, int $milestoneId): void
    {
        $milestone = ProjectMilestone::query()
            ->whereKey($milestoneId)
            ->whereHas('project', fn ($query) => $query->withoutGlobalScopes()->where('workspace_id', $workspaceId))
            ->first();
        if ($milestone === null) {
            throw new GraphScopeNotFound("Milestone {$milestoneId} not found.");
        }

        $issueIds = Issue::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('project_milestone_id', $milestone->id)
            ->pluck('id');

        $graphs->where(function (Builder $query) use ($milestone, $issueIds): void {
            $query->where(fn (Builder $q) => $q
                ->where('owner_type', $milestone->getMorphClass())
                ->where('owner_id', $milestone->id))
                ->orWhere(fn (Builder $q) => $q
                    ->where('owner_type', (new Issue)->getMorphClass())
                    ->whereIn('owner_id', $issueIds));
        });
    }
}
