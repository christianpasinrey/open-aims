<?php

declare(strict_types=1);

namespace App\Modules\Projects\Support;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectActivity;

/**
 * Single source of truth for project activity logging, shared by the UI
 * controllers and the MCP tools so every write path produces the same
 * ProjectActivity rows (and therefore the same notifications / Telegram feed).
 */
final class ProjectActivityRecorder
{
    public function created(Project $project, ?int $actorId): void
    {
        ProjectActivity::create([
            'project_id' => $project->id,
            'actor_user_id' => $actorId,
            'kind' => 'created',
            'payload' => null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Snapshot the diff-relevant fields of a project before a mutation.
     *
     * @return array<string,mixed>
     */
    public function snapshot(Project $project): array
    {
        return [
            'name' => $project->name,
            'description' => $project->description,
            'state' => $project->state?->value,
            'priority' => (int) ($project->priority ?? 0),
            'lead_user_id' => $project->lead_user_id,
            'start_date' => $project->start_date?->toDateString(),
            'target_date' => $project->target_date?->toDateString(),
        ];
    }

    /**
     * Diff before/after of a project and emit one ProjectActivity row per change.
     *
     * @param  array<string,mixed>  $before
     */
    public function record(Project $project, array $before, ?int $actorId): void
    {
        $now = now();
        $base = [
            'project_id' => $project->id,
            'actor_user_id' => $actorId,
            'occurred_at' => $now,
        ];

        if ($before['name'] !== $project->name) {
            ProjectActivity::create($base + [
                'kind' => 'name_changed',
                'payload' => ['from' => $before['name'], 'to' => $project->name],
            ]);
        }

        if (($before['description'] ?? null) !== $project->description) {
            ProjectActivity::create($base + [
                'kind' => 'description_changed',
                'payload' => null,
            ]);
        }

        $newState = $project->state?->value;
        if ($before['state'] !== $newState) {
            ProjectActivity::create($base + [
                'kind' => 'state_changed',
                'payload' => ['from' => $before['state'], 'to' => $newState],
            ]);
        }

        $newPriority = (int) ($project->priority ?? 0);
        if ((int) $before['priority'] !== $newPriority) {
            $labels = [
                0 => 'No priority',
                1 => 'Urgent',
                2 => 'High',
                3 => 'Medium',
                4 => 'Low',
            ];
            ProjectActivity::create($base + [
                'kind' => 'priority_changed',
                'payload' => [
                    'from' => (int) $before['priority'],
                    'from_label' => $labels[(int) $before['priority']] ?? '—',
                    'to' => $newPriority,
                    'to_label' => $labels[$newPriority] ?? '—',
                ],
            ]);
        }

        if ($before['lead_user_id'] !== $project->lead_user_id) {
            if ($project->lead_user_id === null) {
                ProjectActivity::create($base + [
                    'kind' => 'lead_unset',
                    'payload' => null,
                ]);
            } else {
                $u = User::query()->find($project->lead_user_id);
                ProjectActivity::create($base + [
                    'kind' => 'lead_set',
                    'payload' => [
                        'user_id' => $project->lead_user_id,
                        'user_name' => $u?->name,
                    ],
                ]);
            }
        }

        $beforeStart = $before['start_date'];
        $afterStart = $project->start_date?->toDateString();
        if ($beforeStart !== $afterStart) {
            ProjectActivity::create($base + [
                'kind' => 'start_date_changed',
                'payload' => ['from' => $beforeStart, 'to' => $afterStart],
            ]);
        }

        $beforeTarget = $before['target_date'];
        $afterTarget = $project->target_date?->toDateString();
        if ($beforeTarget !== $afterTarget) {
            ProjectActivity::create($base + [
                'kind' => 'target_date_changed',
                'payload' => ['from' => $beforeTarget, 'to' => $afterTarget],
            ]);
        }
    }
}
