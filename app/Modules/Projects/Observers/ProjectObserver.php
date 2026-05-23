<?php

declare(strict_types=1);

namespace App\Modules\Projects\Observers;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Notifications\NewProjectNotification;
use App\Modules\Projects\Notifications\ProjectStatusChangedNotification;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Facades\Notification;

final class ProjectObserver
{
    public function created(Project $project): void
    {
        $actor = auth()->user();
        $actorId = $actor !== null ? (int) $actor->getKey() : null;

        $excludeIds = array_values(array_unique(array_filter([
            $project->creator_user_id !== null ? (int) $project->creator_user_id : null,
            $actorId,
        ], static fn (?int $id): bool => $id !== null)));

        $recipientIds = WorkspaceMember::query()
            ->where('workspace_id', $project->workspace_id)
            ->whereNotIn('user_id', $excludeIds)
            ->pluck('user_id')
            ->all();

        if ($recipientIds === []) {
            return;
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();
        Notification::send($recipients, new NewProjectNotification($project, $actor?->name));
    }

    public function updated(Project $project): void
    {
        if (! $project->wasChanged('state')) {
            return;
        }

        $creatorId = $project->creator_user_id;
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

        $fromState = $project->getOriginal('state');
        $fromState = $fromState instanceof \BackedEnum ? $fromState->value : $fromState;
        $toState = $project->state?->value;

        $creator->notify(new ProjectStatusChangedNotification(
            $project,
            is_string($fromState) ? $fromState : null,
            $toState,
            $actor?->name,
        ));
    }
}
