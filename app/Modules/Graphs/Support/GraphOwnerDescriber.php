<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Support;

use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;

/**
 * What a graph owner is, where it lives in the web, and whether the work it
 * stands for is still open. Queries stay scoped to the bound workspace.
 */
final class GraphOwnerDescriber
{
    private const CLOSED_ISSUE_STATES = ['completed', 'canceled'];

    private const CLOSED_PROJECT_STATES = ['completed', 'canceled'];

    /**
     * @return array{type:string,identifier:string,name:string,url:string,project_slug?:string}|null
     */
    public static function describe(string $ownerType, int $ownerId): ?array
    {
        if ($ownerType === (new Issue)->getMorphClass()) {
            $issue = Issue::query()->with('team:id,key')->find($ownerId);
            if ($issue === null) {
                return null;
            }
            $identifier = ($issue->team?->key ?? '?').'-'.$issue->number;

            return ['type' => 'issue', 'identifier' => $identifier, 'name' => $issue->title, 'url' => '/issues/'.$identifier];
        }

        if ($ownerType === (new Project)->getMorphClass()) {
            $project = Project::query()->find($ownerId);

            return $project === null ? null : [
                'type' => 'project',
                'identifier' => $project->slug,
                'name' => $project->name,
                'url' => '/projects/'.$project->slug,
            ];
        }

        if ($ownerType === (new ProjectMilestone)->getMorphClass()) {
            $milestone = ProjectMilestone::query()->with('project:id,slug')->find($ownerId);
            if ($milestone === null) {
                return null;
            }
            $slug = (string) $milestone->project?->slug;

            return [
                'type' => 'milestone',
                'identifier' => (string) $milestone->id,
                'name' => $milestone->name,
                'project_slug' => $slug,
                'url' => "/projects/{$slug}/milestones/{$milestone->id}",
            ];
        }

        return null;
    }

    /** Open work: an issue not completed, canceled or archived; an active project; an open milestone. */
    public static function isOpen(string $ownerType, int $ownerId): bool
    {
        if ($ownerType === (new Issue)->getMorphClass()) {
            $issue = Issue::query()->with('workflowState:id,type')->find($ownerId);

            return $issue !== null
                && $issue->archived_at === null
                && ! in_array($issue->workflowState?->type, self::CLOSED_ISSUE_STATES, true);
        }

        if ($ownerType === (new Project)->getMorphClass()) {
            $project = Project::query()->find($ownerId);

            return $project !== null
                && ! in_array($project->state?->value, self::CLOSED_PROJECT_STATES, true);
        }

        if ($ownerType === (new ProjectMilestone)->getMorphClass()) {
            $milestone = ProjectMilestone::query()->find($ownerId);

            return $milestone !== null && $milestone->completed_at === null;
        }

        return false;
    }
}
