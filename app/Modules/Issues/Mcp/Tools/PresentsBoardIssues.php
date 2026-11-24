<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Modules\Issues\Enums\IssuePriority;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Support\Str;

/**
 * The shape the `board-*` tools and `issues-start` give an issue.
 *
 * These tools serve clients that draw an interface (the Herdr mod), so a
 * row carries ids and colours and an absolute URL — what `issues-list`
 * leaves out to stay cheap for a language model. One mapping, so a list
 * row and the detail of the same issue can never disagree.
 */
trait PresentsBoardIssues
{
    /** Longest branch name `issueBranchName()` suggests. */
    private const BRANCH_NAME_MAX = 60;

    /**
     * Relations `boardIssueRow()` reads. Eager-load them to avoid N+1.
     *
     * @return list<string>
     */
    protected function boardIssueRelations(): array
    {
        return [
            'team:id,key,color',
            'workflowState:id,name,type,color,position',
            'assignee:id,name',
            'project:id,name,slug',
            'cycle:id,number',
            'labels:id,name,color',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function boardIssueRow(Issue $issue): array
    {
        $identifier = $this->boardIdentifier($issue);
        $priority = $issue->priority ?? IssuePriority::None;
        $state = $issue->workflowState;

        return [
            'identifier' => $identifier,
            'title' => $issue->title,
            'state' => $state === null ? null : [
                'id' => $state->id,
                'name' => $state->name,
                'type' => $state->type,
                'color' => $state->color,
            ],
            'priority' => ['value' => $priority->value, 'label' => $priority->label()],
            'assignee' => $issue->assignee === null ? null : [
                'id' => $issue->assignee->id,
                'name' => $issue->assignee->name,
            ],
            'team' => ['key' => $issue->team?->key, 'color' => $issue->team?->color],
            'project' => $issue->project === null ? null : [
                'slug' => $issue->project->slug,
                'name' => $issue->project->name,
            ],
            'labels' => $issue->labels
                ->map(fn ($label): array => ['name' => $label->name, 'color' => $label->color])
                ->values()
                ->all(),
            'cycle_number' => $issue->cycle?->number,
            'updated_at' => $issue->updated_at?->toIso8601String(),
            'url' => $this->boardIssueUrl($identifier),
        ];
    }

    /**
     * @return array{id:int,name:string,type:string,color:?string,position:int}
     */
    protected function boardStateRow(WorkflowState $state): array
    {
        return [
            'id' => $state->id,
            'name' => $state->name,
            'type' => $state->type,
            'color' => $state->color,
            'position' => $state->position,
        ];
    }

    /**
     * The branch to work an issue on: the one already stored (GitHub pull
     * requests link to the issue by exact match on it), else one derived
     * from the identifier and the title.
     */
    protected function issueBranchName(Issue $issue): string
    {
        $stored = trim((string) $issue->git_branch_name);
        if ($stored !== '') {
            return $stored;
        }

        $prefix = strtolower($this->boardIdentifier($issue));
        $slug = Str::slug((string) $issue->title);
        if ($slug === '') {
            return $prefix;
        }

        return rtrim(substr($prefix.'-'.$slug, 0, self::BRANCH_NAME_MAX), '-');
    }

    protected function boardIssueUrl(string $identifier): string
    {
        return rtrim((string) config('app.url'), '/').'/issues/'.$identifier;
    }

    private function boardIdentifier(Issue $issue): string
    {
        return ($issue->team?->key ?? '').'-'.$issue->number;
    }
}
