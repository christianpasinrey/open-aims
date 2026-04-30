<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Modules\Issues\Enums\IssuePriority;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IssuePreviewController
{
    public function show(string $identifier): JsonResponse
    {
        if (preg_match('/^([A-Za-z]+)-(\d+)$/', $identifier, $m) !== 1) {
            throw new NotFoundHttpException('Invalid issue identifier.');
        }

        $teamKey = strtoupper($m[1]);
        $number = (int) $m[2];

        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', $teamKey)
            ->first();

        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        $issue = Issue::query()
            ->where('team_id', $team->id)
            ->where('number', $number)
            ->with([
                'assignee:id,name,email',
                'workflowState:id,name,type,color',
                'project:id,name,slug,color,icon',
            ])
            ->first();

        if ($issue === null) {
            throw new NotFoundHttpException('Issue not found.');
        }

        return response()->json([
            'identifier' => $team->key.'-'.$issue->number,
            'title' => $issue->title,
            'priority' => (int) ($issue->priority?->value ?? 0),
            'priority_label' => ($issue->priority ?? IssuePriority::None)->label(),
            'state' => $issue->workflowState ? [
                'name' => $issue->workflowState->name,
                'type' => $issue->workflowState->type,
                'color' => $issue->workflowState->color,
            ] : null,
            'assignee' => $issue->assignee ? [
                'id' => $issue->assignee->id,
                'name' => $issue->assignee->name,
                'email' => $issue->assignee->email,
            ] : null,
            'project' => $issue->project ? [
                'name' => $issue->project->name,
                'slug' => $issue->project->slug,
                'color' => $issue->project->color,
                'icon' => $issue->project->icon,
            ] : null,
            'team' => [
                'key' => $team->key,
                'name' => $team->name,
                'color' => $team->color,
            ],
        ]);
    }
}
