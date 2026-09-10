<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Mcp\Tools;

use App\Modules\Graphs\Support\GraphOwnerDescriber;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves and describes the entity a graph belongs to. Every lookup is
 * scoped to the workspace bound by ResolvesWorkspace.
 */
trait ResolvesGraphOwner
{
    /**
     * @return array{0: ?Model, 1: ?string} [owner, error message]
     */
    private function resolveOwner(Workspace $workspace, string $type, string $owner, ?string $projectSlug): array
    {
        $owner = trim($owner);
        $projectSlug = $projectSlug !== null && trim($projectSlug) !== '' ? trim($projectSlug) : null;

        return match ($type) {
            'issue' => $this->resolveIssueOwner($workspace, $owner),
            'project' => $this->resolveProjectOwner($workspace, $owner),
            'milestone' => $this->resolveMilestoneOwner($workspace, $owner, $projectSlug),
            default => [null, "Unknown owner_type '{$type}'. Use issue, project or milestone."],
        };
    }

    /**
     * @return array{0: ?Model, 1: ?string}
     */
    private function resolveIssueOwner(Workspace $workspace, string $identifier): array
    {
        if (preg_match('/^([A-Za-z]+)-(\d+)$/', $identifier, $matches) !== 1) {
            return [null, "Invalid issue identifier '{$identifier}'. Expected TEAMKEY-N (e.g. \"DER-69\")."];
        }

        $key = strtoupper($matches[1]);
        $team = Team::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('key', $key)
            ->first();
        $issue = $team === null ? null : Issue::query()
            ->where('team_id', $team->getKey())
            ->where('number', (int) $matches[2])
            ->first();

        return $issue === null ? [null, "{$key}-{$matches[2]} not found."] : [$issue, null];
    }

    /**
     * @return array{0: ?Model, 1: ?string}
     */
    private function resolveProjectOwner(Workspace $workspace, string $slug): array
    {
        $project = Project::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('slug', $slug)
            ->first();

        return $project === null ? [null, "Project '{$slug}' not found."] : [$project, null];
    }

    /**
     * @return array{0: ?Model, 1: ?string}
     */
    private function resolveMilestoneOwner(Workspace $workspace, string $reference, ?string $projectSlug): array
    {
        $projectIds = Project::query()
            ->where('workspace_id', $workspace->getKey())
            ->when($projectSlug !== null, fn ($query) => $query->where('slug', $projectSlug))
            ->pluck('id');

        if ($projectSlug !== null && $projectIds->isEmpty()) {
            return [null, "Project '{$projectSlug}' not found."];
        }

        if (ctype_digit($reference)) {
            $milestone = ProjectMilestone::query()
                ->whereIn('project_id', $projectIds)
                ->whereKey((int) $reference)
                ->first();

            return $milestone === null ? [null, "Milestone {$reference} not found."] : [$milestone, null];
        }

        if ($projectSlug === null) {
            return [null, 'A milestone name needs project_slug. Pass the milestone id otherwise.'];
        }

        $matches = ProjectMilestone::query()
            ->whereIn('project_id', $projectIds)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($reference)])
            ->get();

        return match ($matches->count()) {
            1 => [$matches->first(), null],
            0 => [null, "Milestone '{$reference}' not found in project '{$projectSlug}'."],
            default => [null, "Milestone name '{$reference}' is ambiguous in project '{$projectSlug}'. Pass its id."],
        };
    }

    /**
     * @return array{type:string,identifier:string,name:string,url:string,project_slug?:string}|null
     */
    private function describeOwner(string $ownerType, int $ownerId): ?array
    {
        return GraphOwnerDescriber::describe($ownerType, $ownerId);
    }
}
