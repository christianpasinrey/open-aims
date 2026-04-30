<?php

declare(strict_types=1);

namespace App\Modules\Initiatives\Http\Controllers;

use App\Models\User;
use App\Modules\Initiatives\Models\Initiative;
use App\Modules\Projects\Models\Project;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class InitiativeDetailController
{
    public function show(Request $request, string $slug): Response
    {
        $tab = $request->query('tab');
        $tab = is_string($tab) && in_array($tab, ['overview', 'projects', 'activity'], true)
            ? $tab
            : 'overview';

        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $initiative = Initiative::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->with([
                'owner:id,name,email',
                'parent:id,name,slug',
                'children:id,name,slug,parent_initiative_id,state,color,icon',
                'members.user:id,name,email',
            ])
            ->first();

        if ($initiative === null) {
            throw new NotFoundHttpException('Initiative not found.');
        }

        $projects = $initiative
            ->projects()
            ->with(['lead:id,name,email'])
            ->withCount([
                'issues as total_issues',
                'issues as completed_issues' => static fn ($q) => $q->whereHas(
                    'workflowState',
                    static fn ($w) => $w->where('type', 'completed'),
                ),
            ])
            ->orderBy('initiative_projects.sort_order')
            ->orderBy('projects.name')
            ->get();

        $totalIssues = 0;
        $completedIssues = 0;
        foreach ($projects as $p) {
            $totalIssues += (int) ($p->total_issues ?? 0);
            $completedIssues += (int) ($p->completed_issues ?? 0);
        }
        $completion = $totalIssues > 0 ? (int) round(($completedIssues / $totalIssues) * 100) : 0;

        // Workspace members for owner picker / member picker.
        $memberIds = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('user_id');
        $members = User::query()
            ->whereIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])
            ->all();

        // Available projects for the "+ Add project" picker (workspace projects
        // not yet attached to this initiative).
        $attachedIds = $projects->pluck('id')->all();
        $availableProjects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->when($attachedIds !== [], fn ($q) => $q->whereNotIn('id', $attachedIds))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color', 'icon', 'state'])
            ->map(static fn (Project $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'color' => $p->color,
                'icon' => $p->icon,
                'state' => $p->state?->value,
            ])
            ->all();

        return Inertia::render('initiatives/Show', [
            'tab' => $tab,
            'initiative' => [
                'id' => $initiative->id,
                'name' => $initiative->name,
                'slug' => $initiative->slug,
                'description' => $initiative->description,
                'state' => $initiative->state?->value,
                'color' => $initiative->color,
                'icon' => $initiative->icon,
                'start_date' => $initiative->start_date?->toDateString(),
                'target_date' => $initiative->target_date?->toDateString(),
                'completed_at' => $initiative->completed_at?->toIso8601String(),
                'owner' => $initiative->owner ? [
                    'id' => $initiative->owner->id,
                    'name' => $initiative->owner->name,
                    'email' => $initiative->owner->email,
                ] : null,
                'parent' => $initiative->parent ? [
                    'id' => $initiative->parent->id,
                    'name' => $initiative->parent->name,
                    'slug' => $initiative->parent->slug,
                ] : null,
                'children' => $initiative->children->map(fn ($c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'state' => $c->state?->value,
                    'color' => $c->color,
                    'icon' => $c->icon,
                ])->all(),
                'members' => $initiative->members->map(fn ($m): array => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                    'role' => $m->role,
                ])->filter(static fn ($m) => $m['id'] !== null)->values()->all(),
            ],
            'projects' => $projects->map(function (Project $p): array {
                $total = (int) ($p->total_issues ?? 0);
                $completed = (int) ($p->completed_issues ?? 0);
                $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'state' => $p->state?->value,
                    'color' => $p->color,
                    'icon' => $p->icon,
                    'target_date' => $p->target_date?->toDateString(),
                    'lead' => $p->lead ? [
                        'id' => $p->lead->id,
                        'name' => $p->lead->name,
                        'email' => $p->lead->email,
                    ] : null,
                    'total_issues' => $total,
                    'completed_issues' => $completed,
                    'progress' => $percent,
                ];
            })->all(),
            'available_projects' => $availableProjects,
            'members' => $members,
            'progress' => [
                'total_projects' => $projects->count(),
                'total_issues' => $totalIssues,
                'completed_issues' => $completedIssues,
                'percent' => $completion,
            ],
        ]);
    }
}
