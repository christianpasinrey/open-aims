<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Models\User;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mutations on projects from the repo-style UI: create + partial update +
 * milestone create. Workspace scoping is applied via the global
 * BelongsToWorkspace scope on Project; we still resolve the active workspace
 * to know where to attach new rows. No granular permissions yet — auth +
 * verified middleware on the route group is the gate.
 */
final class ProjectWriteController
{
    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace();

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'team_keys' => 'nullable|array',
            'team_keys.*' => 'string|max:16',
            'lead_user_id' => 'nullable|integer|exists:users,id',
            'state' => 'nullable|in:backlog,planned,started,paused,completed,canceled',
            'color' => 'nullable|string|max:9',
            'icon' => 'nullable|string|max:64',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
        ]);

        $teamKeys = collect($data['team_keys'] ?? [])
            ->map(static fn (string $k): string => strtoupper($k))
            ->unique()
            ->values()
            ->all();

        $teamIds = [];
        if ($teamKeys !== []) {
            $teamIds = Team::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('key', $teamKeys)
                ->pluck('id')
                ->all();
        }

        $project = DB::transaction(function () use ($workspace, $data, $teamIds): Project {
            $slug = $this->uniqueSlug($workspace->id, $data['name']);

            $state = $data['state'] ?? 'backlog';
            $completedAt = in_array($state, ['completed', 'canceled'], true) ? now() : null;

            $project = Project::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'state' => $state,
                'lead_user_id' => $data['lead_user_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'target_date' => $data['target_date'] ?? null,
                'color' => $data['color'] ?? '#6366f1',
                'icon' => $data['icon'] ?? null,
                'completed_at' => $completedAt,
            ]);

            if ($teamIds !== []) {
                $project->teams()->sync($teamIds);
            }

            ProjectActivity::create([
                'project_id' => $project->id,
                'actor_user_id' => request()->user()?->getKey(),
                'kind' => 'created',
                'payload' => null,
                'occurred_at' => now(),
            ]);

            return $project;
        });

        return redirect()->route('projects.show', ['slug' => $project->slug]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|nullable|string',
            'lead_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'state' => 'sometimes|in:backlog,planned,started,paused,completed,canceled',
            'priority' => 'sometimes|integer|in:0,1,2,3,4',
            'color' => 'sometimes|string|max:9',
            'icon' => 'sometimes|nullable|string|max:64',
            'start_date' => 'sometimes|nullable|date',
            'target_date' => 'sometimes|nullable|date',
            'completed_at' => 'sometimes|nullable|date',
        ]);

        // Auto-manage completed_at on state transitions unless the caller
        // sent its own value explicitly.
        if (array_key_exists('state', $data) && ! array_key_exists('completed_at', $data)) {
            $newState = $data['state'];
            if (in_array($newState, ['completed', 'canceled'], true)) {
                if ($project->completed_at === null) {
                    $data['completed_at'] = now();
                }
            } else {
                $data['completed_at'] = null;
            }
        }

        $before = [
            'name' => $project->name,
            'description' => $project->description,
            'state' => $project->state?->value,
            'priority' => (int) ($project->priority ?? 0),
            'lead_user_id' => $project->lead_user_id,
            'start_date' => $project->start_date?->toDateString(),
            'target_date' => $project->target_date?->toDateString(),
        ];

        DB::transaction(function () use ($project, $data, $before, $request): void {
            $project->fill($data)->save();
            $this->recordProjectChanges($project->fresh(), $before, $request->user()?->getKey());
        });

        return back();
    }

    public function storeMilestone(Request $request, string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $sortOrder = $data['sort_order'] ?? null;
        if ($sortOrder === null) {
            $sortOrder = ((int) ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->max('sort_order')) + 1;
        }

        $milestone = ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'sort_order' => $sortOrder,
        ]);

        ProjectActivity::create([
            'project_id' => $project->id,
            'actor_user_id' => $request->user()?->getKey(),
            'kind' => 'milestone_added',
            'payload' => [
                'milestone_id' => $milestone->id,
                'milestone_name' => $milestone->name,
            ],
            'occurred_at' => now(),
        ]);

        return back();
    }

    /**
     * Soft-delete a project together with its issues and milestones.
     *
     * Items are stamped with the same deleted_at so we can restore the
     * exact set the user trashed, even if other items were soft-deleted
     * independently later.
     */
    public function destroy(string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);
        $now = now();
        $actorId = request()->user()?->getKey();

        DB::transaction(function () use ($project, $now, $actorId): void {
            Issue::query()
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            ProjectActivity::create([
                'project_id' => $project->id,
                'actor_user_id' => $actorId,
                'kind' => 'trashed',
                'payload' => null,
                'occurred_at' => $now,
            ]);

            $project->deleted_at = $now;
            $project->save();
        });

        return redirect()->route('projects.index');
    }

    public function restore(string $slug): RedirectResponse
    {
        $project = $this->resolveTrashedProject($slug);

        if ($project->deleted_at === null) {
            return redirect()->route('projects.show', ['slug' => $slug]);
        }

        $deletedAt = Carbon::parse($project->deleted_at);
        // Match the cascade we did at delete-time. ±1 second tolerance covers
        // the (tiny) window where update() and save() may differ by ms.
        $from = $deletedAt->copy()->subSecond();
        $to = $deletedAt->copy()->addSecond();

        $actorId = request()->user()?->getKey();
        DB::transaction(function () use ($project, $from, $to, $actorId): void {
            Issue::query()
                ->withoutGlobalScopes()
                ->where('project_id', $project->id)
                ->whereBetween('deleted_at', [$from, $to])
                ->update(['deleted_at' => null]);

            ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->whereBetween('deleted_at', [$from, $to])
                ->update(['deleted_at' => null]);

            ProjectActivity::create([
                'project_id' => $project->id,
                'actor_user_id' => $actorId,
                'kind' => 'restored',
                'payload' => null,
                'occurred_at' => now(),
            ]);

            $project->deleted_at = null;
            $project->save();
        });

        return redirect()->route('projects.show', ['slug' => $slug]);
    }

    public function forceDestroy(string $slug): RedirectResponse
    {
        $project = $this->resolveTrashedProject($slug);
        $project->forceDelete();

        return redirect()->route('trash.index');
    }

    /**
     * Attach a workspace member to the project (role: contributor by default).
     */
    public function attachMember(Request $request, string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);
        $workspace = $this->workspace();

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'sometimes|in:lead,contributor',
        ]);

        $isMember = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $data['user_id'])
            ->exists();
        if (! $isMember) {
            abort(403, 'User is not a member of this workspace.');
        }

        $member = ProjectMember::query()->firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $data['user_id']],
            ['role' => $data['role'] ?? 'contributor'],
        );

        if ($member->wasRecentlyCreated) {
            $u = User::query()->find($data['user_id']);
            ProjectActivity::create([
                'project_id' => $project->id,
                'actor_user_id' => $request->user()?->getKey(),
                'kind' => 'member_added',
                'payload' => [
                    'user_id' => $data['user_id'],
                    'user_name' => $u?->name,
                ],
                'occurred_at' => now(),
            ]);
        }

        return back();
    }

    public function detachMember(string $slug, int $userId): RedirectResponse
    {
        $project = $this->resolveProject($slug);

        $deleted = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $userId)
            ->delete();

        if ($deleted > 0) {
            $u = User::query()->find($userId);
            ProjectActivity::create([
                'project_id' => $project->id,
                'actor_user_id' => request()->user()?->getKey(),
                'kind' => 'member_removed',
                'payload' => [
                    'user_id' => $userId,
                    'user_name' => $u?->name,
                ],
                'occurred_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Attach a team-scoped label to the project. The label must belong to one
     * of the teams the project is associated with.
     */
    public function attachLabel(Request $request, string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);

        $data = $request->validate([
            'label_id' => 'required|integer|exists:labels,id',
        ]);

        $teamIds = $project->teams()->pluck('teams.id');
        $belongsToProjectTeam = Label::query()
            ->where('id', $data['label_id'])
            ->whereIn('team_id', $teamIds)
            ->exists();
        if (! $belongsToProjectTeam) {
            abort(403, 'Label does not belong to one of the project teams.');
        }

        $alreadyAttached = $project->labels()->where('labels.id', $data['label_id'])->exists();
        $project->labels()->syncWithoutDetaching([$data['label_id']]);

        if (! $alreadyAttached) {
            $label = Label::query()->find($data['label_id']);
            ProjectActivity::create([
                'project_id' => $project->id,
                'actor_user_id' => $request->user()?->getKey(),
                'kind' => 'label_added',
                'payload' => [
                    'label_id' => $data['label_id'],
                    'label_name' => $label?->name,
                    'label_color' => $label?->color,
                ],
                'occurred_at' => now(),
            ]);
        }

        return back();
    }

    public function detachLabel(string $slug, int $labelId): RedirectResponse
    {
        $project = $this->resolveProject($slug);
        $wasAttached = $project->labels()->where('labels.id', $labelId)->exists();
        $project->labels()->detach($labelId);

        if ($wasAttached) {
            $label = Label::query()->find($labelId);
            ProjectActivity::create([
                'project_id' => $project->id,
                'actor_user_id' => request()->user()?->getKey(),
                'kind' => 'label_removed',
                'payload' => [
                    'label_id' => $labelId,
                    'label_name' => $label?->name,
                    'label_color' => $label?->color,
                ],
                'occurred_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Diff before/after of a project update and emit one ProjectActivity row
     * per significant change.
     *
     * @param  array<string,mixed>  $before
     */
    private function recordProjectChanges(Project $project, array $before, ?int $actorId): void
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

    private function resolveProject(string $slug): Project
    {
        $workspace = $this->workspace();

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->first();
        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        return $project;
    }

    private function resolveTrashedProject(string $slug): Project
    {
        $workspace = $this->workspace();

        $project = Project::query()
            ->withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->first();
        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        return $project;
    }

    private function uniqueSlug(int $workspaceId, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'project';
        }
        // repo-style: keep the human slug stable but disambiguate per workspace
        // with a 6-char random suffix. Loop on collision (very rare).
        for ($i = 0; $i < 5; $i++) {
            $slug = $base.'-'.Str::lower(Str::random(6));
            $exists = Project::query()
                ->withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('slug', $slug)
                ->exists();
            if (! $exists) {
                return $slug;
            }
        }

        // Last-resort fallback: timestamp suffix
        return $base.'-'.now()->format('YmdHis');
    }

    private function workspace(): Workspace
    {
        if (! app()->bound('current.workspace')) {
            abort(404, 'No active workspace.');
        }
        $w = app('current.workspace');
        if (! $w instanceof Workspace) {
            abort(404, 'No active workspace.');
        }

        return $w;
    }
}
