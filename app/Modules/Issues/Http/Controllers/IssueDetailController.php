<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Integrations\Github\Models\GithubBranch;
use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Integrations\Github\Models\GithubLink;
use App\Modules\Integrations\Github\Models\GithubLinkedPullRequest;
use App\Modules\Integrations\Github\Models\GithubPullRequest;
use App\Modules\Issues\Enums\IssuePriority;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Issues\Models\IssueRelation;
use App\Modules\Issues\Models\IssueResource;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IssueDetailController
{
    public function show(string $identifier): Response
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
                'creator:id,name,email',
                'workflowState:id,name,type,color,position',
                'labels:id,name,color',
                'project:id,name,slug,color,icon',
                'cycle:id,number,name,starts_at,ends_at',
                'parent:id,team_id,number,title',
                'children:id,team_id,number,title,workflow_state_id,priority,assignee_user_id',
                'children.workflowState:id,name,type,color',
                'children.assignee:id,name',
                'resources.media',
                'resources.creator:id,name,email',
            ])
            ->first();

        if ($issue === null) {
            throw new NotFoundHttpException('Issue not found.');
        }

        $comments = Comment::query()
            ->where('issue_id', $issue->id)
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->get();

        $states = WorkflowState::query()
            ->where('team_id', $team->id)
            ->orderBy('position')
            ->get(['id', 'name', 'type', 'color', 'position']);

        $cycles = Cycle::query()
            ->where('team_id', $team->id)
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get(['id', 'number', 'name', 'starts_at', 'ends_at']);

        $labels = Label::query()
            ->where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color', 'icon']);

        $legacyLinkedPullRequests = GithubLinkedPullRequest::query()
            ->where('issue_id', $issue->id)
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->get();

        // Polymorphic links + available sources for the picker.
        $polymorphic = $this->loadPolymorphicLinks($issue);
        $linkedBranches = $polymorphic['linked_branches'];
        $linkedPullRequests = $polymorphic['linked_pull_requests'];
        $availableSources = $this->loadAvailableSources($workspace);

        $activities = IssueActivity::query()
            ->where('issue_id', $issue->id)
            ->with('actor:id,name,email')
            ->orderBy('occurred_at')
            ->get();

        $relationsOut = $this->loadRelations($issue);

        // Latest plan attached via MCP (or any future Plan upload path). We
        // surface the summary on every issue payload, plus the full body
        // when small enough to render inline.
        $latestPlanResource = IssueResource::query()
            ->where('issue_id', $issue->id)
            ->where('is_plan', true)
            ->with('media')
            ->latest()
            ->first();
        [$latestPlan, $latestPlanContent, $planTooLarge] = $this->buildLatestPlan($latestPlanResource);

        return Inertia::render('issues/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ],
            'issue' => [
                'id' => $issue->id,
                'identifier' => $team->key.'-'.$issue->number,
                'number' => $issue->number,
                'title' => $issue->title,
                'description' => $issue->description,
                'git_branch_name' => $issue->git_branch_name,
                'priority' => (int) ($issue->priority?->value ?? 0),
                'priority_label' => ($issue->priority ?? IssuePriority::None)->label(),
                'estimate' => $issue->estimate,
                'due_date' => $issue->due_date?->toDateString(),
                'started_at' => $issue->started_at?->toIso8601String(),
                'completed_at' => $issue->completed_at?->toIso8601String(),
                'canceled_at' => $issue->canceled_at?->toIso8601String(),
                'state' => $issue->workflowState ? [
                    'id' => $issue->workflowState->id,
                    'name' => $issue->workflowState->name,
                    'type' => $issue->workflowState->type,
                    'color' => $issue->workflowState->color,
                ] : null,
                'assignee' => $issue->assignee ? [
                    'id' => $issue->assignee->id,
                    'name' => $issue->assignee->name,
                    'email' => $issue->assignee->email,
                ] : null,
                'creator' => $issue->creator ? [
                    'id' => $issue->creator->id,
                    'name' => $issue->creator->name,
                    'email' => $issue->creator->email,
                ] : null,
                'project' => $issue->project ? [
                    'id' => $issue->project->id,
                    'name' => $issue->project->name,
                    'slug' => $issue->project->slug,
                    'color' => $issue->project->color,
                    'icon' => $issue->project->icon,
                ] : null,
                'cycle' => $issue->cycle ? [
                    'id' => $issue->cycle->id,
                    'number' => $issue->cycle->number,
                    'name' => $issue->cycle->name,
                    'starts_at' => $issue->cycle->starts_at?->toDateString(),
                    'ends_at' => $issue->cycle->ends_at?->toDateString(),
                ] : null,
                'labels' => $issue->labels->map(fn ($l): array => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                ])->all(),
                'parent' => $issue->parent ? [
                    'identifier' => $team->key.'-'.$issue->parent->number,
                    'title' => $issue->parent->title,
                ] : null,
                'children' => $issue->children->map(fn (Issue $c): array => [
                    'id' => $c->id,
                    'identifier' => $team->key.'-'.$c->number,
                    'title' => $c->title,
                    'priority' => (int) ($c->priority?->value ?? 0),
                    'state' => $c->workflowState ? [
                        'name' => $c->workflowState->name,
                        'type' => $c->workflowState->type,
                        'color' => $c->workflowState->color,
                    ] : null,
                    'assignee' => $c->assignee ? [
                        'id' => $c->assignee->id,
                        'name' => $c->assignee->name,
                    ] : null,
                ])->all(),
                'resources' => $issue->resources->map(function ($r): array {
                    $media = $r->getFirstMedia('attachment');

                    return [
                        'id' => $r->id,
                        'type' => $r->type,
                        'is_plan' => (bool) $r->is_plan,
                        'name' => $r->name,
                        'url' => $r->type === 'link'
                            ? $r->url
                            : ($media?->getFullUrl() ?? null),
                        'mime_type' => $media?->mime_type,
                        'size' => $media ? (int) $media->size : null,
                        'created_at' => $r->created_at?->toIso8601String(),
                        'creator' => $r->creator ? [
                            'id' => $r->creator->id,
                            'name' => $r->creator->name,
                            'email' => $r->creator->email,
                        ] : null,
                    ];
                })->all(),
                'latest_plan' => $latestPlan,
                'latest_plan_content' => $latestPlanContent,
                'plan_too_large' => $planTooLarge,
                'created_at' => $issue->created_at?->toIso8601String(),
                'updated_at' => $issue->updated_at?->toIso8601String(),
            ],
            'comments' => $comments->map(fn (Comment $c): array => [
                'id' => $c->id,
                'body' => $c->body,
                'user' => $c->user ? [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'email' => $c->user->email,
                ] : null,
                'created_at' => $c->created_at?->toIso8601String(),
                'edited_at' => $c->edited_at?->toIso8601String(),
            ])->all(),
            'states' => $states->map(fn (WorkflowState $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'color' => $s->color,
                'position' => $s->position,
            ])->all(),
            'priorities' => $this->priorityOptions(),
            'cycles' => $cycles->map(fn (Cycle $c): array => [
                'id' => $c->id,
                'number' => $c->number,
                'name' => $c->name,
                'starts_at' => $c->starts_at?->toDateString(),
                'ends_at' => $c->ends_at?->toDateString(),
            ])->all(),
            'labels' => $labels->map(fn (Label $l): array => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
            ])->all(),
            'projects' => $projects->map(fn (Project $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'color' => $p->color,
                'icon' => $p->icon,
            ])->all(),
            // Legacy view of linked PRs sourced from
            // `github_linked_pull_requests`. Kept for back-compat with the
            // existing LinkedPullRequests right-rail component while the
            // new polymorphic picker rolls out.
            'legacy_linked_pull_requests' => $legacyLinkedPullRequests
                ->map(fn (GithubLinkedPullRequest $pr): array => [
                    'id' => $pr->id,
                    'number' => $pr->pr_number,
                    'title' => $pr->pr_title,
                    'state' => $pr->pr_state,
                    'url' => $pr->pr_url,
                    'branch_name' => $pr->branch_name,
                    'author_login' => $pr->author_login,
                    'opened_at' => $pr->opened_at?->toIso8601String(),
                    'closed_at' => $pr->closed_at?->toIso8601String(),
                    'merged_at' => $pr->merged_at?->toIso8601String(),
                ])
                ->all(),
            'linked_branches' => $linkedBranches,
            'linked_pull_requests' => $linkedPullRequests,
            'available_github_sources' => $availableSources,
            'activities' => $activities->map(fn (IssueActivity $a): array => [
                'id' => $a->id,
                'kind' => $a->kind,
                'payload' => $a->payload,
                'occurred_at' => $a->occurred_at?->toIso8601String(),
                'actor' => $a->actor ? [
                    'id' => $a->actor->id,
                    'name' => $a->actor->name,
                    'email' => $a->actor->email,
                ] : null,
            ])->all(),
            'relations' => $relationsOut,
        ]);
    }

    /**
     * Build the front-end payload for the latest plan attached to an issue.
     *
     * Returns a 3-tuple [summary, fullContent, tooLarge]:
     *   - summary: null | array with id/format/name/url/content_preview/uploaded_at
     *   - fullContent: full body string, or null if missing OR larger than the
     *     inline-render cap (200 KB).
     *   - tooLarge: true when the file body exists but exceeds the cap.
     *
     * @return array{0: ?array, 1: ?string, 2: bool}
     */
    private function buildLatestPlan(?IssueResource $resource): array
    {
        if ($resource === null) {
            return [null, null, false];
        }

        $media = $resource->getFirstMedia('attachment');
        $name = $resource->name;
        $format = str_ends_with(strtolower($name), '.html') ? 'html' : 'md';

        $body = null;
        if ($media !== null) {
            $path = $media->getPath();
            if (is_string($path) && $path !== '' && is_readable($path)) {
                $body = (string) @file_get_contents($path);
            }
        }

        $preview = $body !== null ? mb_substr($body, 0, 500) : '';

        // Cap inline rendering at 200 KB; bigger files are linked-only.
        $maxBytes = 200 * 1024;
        $tooLarge = false;
        $fullContent = null;
        if ($body !== null) {
            if (strlen($body) > $maxBytes) {
                $tooLarge = true;
            } else {
                $fullContent = $body;
            }
        }

        $summary = [
            'id' => (int) $resource->id,
            'format' => $format,
            'name' => $name,
            'url' => $media?->getFullUrl(),
            'content_preview' => $preview,
            'uploaded_at' => $resource->created_at?->toIso8601String(),
        ];

        return [$summary, $fullContent, $tooLarge];
    }

    /**
     * @return array{
     *   blocks: list<array<string,mixed>>,
     *   blocked_by: list<array<string,mixed>>,
     *   related: list<array<string,mixed>>,
     *   duplicate_of: list<array<string,mixed>>,
     * }
     */
    private function loadRelations(Issue $issue): array
    {
        $teamKey = $issue->team?->key ?? '';

        $outgoing = IssueRelation::query()
            ->where('source_issue_id', $issue->id)
            ->with(['target.workflowState:id,name,type,color', 'target.team:id,key'])
            ->get();
        $incoming = IssueRelation::query()
            ->where('target_issue_id', $issue->id)
            ->with(['source.workflowState:id,name,type,color', 'source.team:id,key'])
            ->get();

        $shape = static fn (Issue $other, ?string $key): array => [
            'identifier' => ($key ?? '').'-'.$other->number,
            'title' => $other->title,
            'state' => $other->workflowState ? [
                'name' => $other->workflowState->name,
                'type' => $other->workflowState->type,
                'color' => $other->workflowState->color,
            ] : null,
        ];

        $blocks = $outgoing->where('type', 'blocks')
            ->map(fn (IssueRelation $r): array => $shape($r->target, $r->target->team?->key))
            ->values()->all();
        $blockedBy = $incoming->where('type', 'blocks')
            ->map(fn (IssueRelation $r): array => $shape($r->source, $r->source->team?->key))
            ->values()->all();

        $related = $outgoing->where('type', 'related')
            ->map(fn (IssueRelation $r): array => $shape($r->target, $r->target->team?->key))
            ->concat(
                $incoming->where('type', 'related')
                    ->map(fn (IssueRelation $r): array => $shape($r->source, $r->source->team?->key)),
            )
            ->unique('identifier')
            ->values()->all();

        $duplicateOf = $outgoing->where('type', 'duplicate')
            ->map(fn (IssueRelation $r): array => $shape($r->target, $r->target->team?->key))
            ->values()->all();

        return [
            'blocks' => $blocks,
            'blocked_by' => $blockedBy,
            'related' => $related,
            'duplicate_of' => $duplicateOf,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function priorityOptions(): array
    {
        $out = [];
        foreach (IssuePriority::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /**
     * Load polymorphic GitHub links for an issue, eagerly resolving
     * each source row in two queries (one per kind) and shaping the
     * payload for the right-rail picker.
     *
     * @return array{
     *   linked_branches: list<array<string,mixed>>,
     *   linked_pull_requests: list<array<string,mixed>>,
     * }
     */
    private function loadPolymorphicLinks(Issue $issue): array
    {
        /** @var Collection<int,GithubLink> $links */
        $links = $issue->githubLinks()->orderByDesc('created_at')->get();

        $branchIds = $links->where('source_type', 'branch')->pluck('source_id')->all();
        $prIds = $links->where('source_type', 'pull_request')->pluck('source_id')->all();

        /** @var Collection<int,GithubBranch> $branches */
        $branches = $branchIds === []
            ? collect()
            : GithubBranch::query()->with('repo:id,full_name')->whereIn('id', $branchIds)->get()->keyBy('id');
        /** @var Collection<int,GithubPullRequest> $prs */
        $prs = $prIds === []
            ? collect()
            : GithubPullRequest::query()->whereIn('id', $prIds)->get()->keyBy('id');

        $linkedBranches = $links
            ->where('source_type', 'branch')
            ->map(static function (GithubLink $link) use ($branches): ?array {
                $b = $branches->get($link->source_id);
                if (! $b instanceof GithubBranch) {
                    return null;
                }
                $repoName = $b->repo?->full_name ?? '';
                $url = $repoName !== ''
                    ? "https://github.com/{$repoName}/tree/".rawurlencode($b->name)
                    : null;

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'head_sha' => $b->head_sha,
                    'repo_full_name' => $repoName,
                    'html_url' => $url,
                    'last_pushed_at' => $b->last_pushed_at?->toIso8601String(),
                    'link_id' => $link->id,
                    'auto' => (bool) $link->auto,
                    'linked_at' => $link->created_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $linkedPullRequests = $links
            ->where('source_type', 'pull_request')
            ->map(static function (GithubLink $link) use ($prs): ?array {
                $pr = $prs->get($link->source_id);
                if (! $pr instanceof GithubPullRequest) {
                    return null;
                }

                return [
                    'id' => $pr->id,
                    'number' => $pr->number,
                    'title' => $pr->title,
                    'state' => $pr->state,
                    'merged' => (bool) $pr->merged,
                    'head_branch_name' => $pr->head_branch_name,
                    'html_url' => $pr->html_url,
                    'link_id' => $link->id,
                    'auto' => (bool) $link->auto,
                    'linked_at' => $link->created_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'linked_branches' => $linkedBranches,
            'linked_pull_requests' => $linkedPullRequests,
        ];
    }

    /**
     * Build the picker pool: the top 100 branches (by last_pushed_at)
     * and the top 100 pull requests (by opened_at) from the workspace's
     * GitHub installations, flattened into a single list of items the
     * client can filter inline without round-trips.
     *
     * @return list<array<string,mixed>>
     */
    private function loadAvailableSources(Workspace $workspace): array
    {
        $installationIds = GithubInstallation::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('id');
        if ($installationIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int,GithubBranch> $branches */
        $branches = GithubBranch::query()
            ->with('repo:id,full_name')
            ->whereHas('repo', static function ($q) use ($installationIds): void {
                $q->whereIn('installation_id', $installationIds);
            })
            ->orderByDesc('last_pushed_at')
            ->limit(100)
            ->get();

        /** @var Collection<int,GithubPullRequest> $prs */
        $prs = GithubPullRequest::query()
            ->with('repo:id,full_name')
            ->whereHas('repo', static function ($q) use ($installationIds): void {
                $q->whereIn('installation_id', $installationIds);
            })
            ->orderByDesc('opened_at')
            ->limit(100)
            ->get();

        $items = [];
        foreach ($branches as $b) {
            $items[] = [
                'kind' => 'branch',
                'id' => $b->id,
                'label' => $b->name,
                'sublabel' => $b->repo?->full_name ?? '',
            ];
        }
        foreach ($prs as $pr) {
            $items[] = [
                'kind' => 'pull_request',
                'id' => $pr->id,
                'label' => '#'.$pr->number.' '.$pr->title,
                'sublabel' => ($pr->repo?->full_name ?? '').' · '.($pr->head_branch_name ?? ''),
            ];
        }

        return $items;
    }
}
