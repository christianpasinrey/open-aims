<?php

declare(strict_types=1);

namespace App\Modules\Issues\Observers;

use App\Models\User;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Notifications\IssueAssignedNotification;
use App\Modules\Issues\Notifications\IssueStatusChangedNotification;
use App\Modules\Teams\Models\WorkflowState;

final class IssueObserver
{
    public function created(Issue $issue): void
    {
        if ($issue->assignee_user_id !== null) {
            $this->notifyAssignee($issue, (int) $issue->assignee_user_id);
        }
    }

    public function updated(Issue $issue): void
    {
        if ($issue->wasChanged('assignee_user_id') && $issue->assignee_user_id !== null) {
            $this->notifyAssignee($issue, (int) $issue->assignee_user_id);
        }

        if ($issue->wasChanged('workflow_state_id')) {
            $this->notifyStatusChange($issue);
        }
    }

    private function notifyAssignee(Issue $issue, int $assigneeId): void
    {
        $actor = auth()->user();
        if ($actor !== null && (int) $actor->getKey() === $assigneeId) {
            return;
        }

        $assignee = User::query()->find($assigneeId);
        $assignee?->notify(new IssueAssignedNotification($issue, $actor?->name));
    }

    private function notifyStatusChange(Issue $issue): void
    {
        $creatorId = $issue->creator_user_id;
        if ($creatorId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor !== null && (int) $actor->getKey() === (int) $creatorId) {
            return;
        }

        $creator = User::query()->find($creatorId);
        if ($creator === null) {
            return;
        }

        $fromState = WorkflowState::query()->find($issue->getOriginal('workflow_state_id'))?->name;
        $toState = $issue->workflowState?->name
            ?? WorkflowState::query()->find($issue->workflow_state_id)?->name;

        $creator->notify(new IssueStatusChangedNotification($issue, $fromState, $toState, $actor?->name));
    }
}
