<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github\Http\Controllers;

use App\Modules\Integrations\Github\Models\GithubBranch;
use App\Modules\Integrations\Github\Models\GithubInstallation;
use App\Modules\Integrations\Github\Models\GithubLink;
use App\Modules\Integrations\Github\Models\GithubPullRequest;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manual create + delete for polymorphic GitHub links written into the
 * `github_links` table from the issue or project right-rail picker.
 *
 * Resolves both the source row (a branch or PR) and the linkable
 * (Issue or Project), enforces workspace scoping, and emits a matching
 * activity row so the UI activity feed reflects the link.
 */
final class GithubLinkController
{
    /** @var array<string,class-string<Model>> */
    private const LINKABLE_MAP = [
        'issue' => Issue::class,
        'project' => Project::class,
    ];

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $workspace = $this->workspace();

        $data = $request->validate([
            'source_type' => 'required|in:branch,pull_request',
            'source_id' => 'required|integer|min:1',
            'linkable_type' => 'required|in:issue,project',
            'linkable_id' => 'required|integer|min:1',
        ]);

        /** @var class-string<Model> $linkableClass */
        $linkableClass = self::LINKABLE_MAP[$data['linkable_type']];

        // Resolve the linkable and enforce workspace ownership. Both
        // Issue and Project carry a workspace_id column.
        /** @var Model|null $linkable */
        $linkable = $linkableClass::query()
            ->where('id', $data['linkable_id'])
            ->where('workspace_id', $workspace->id)
            ->first();
        if ($linkable === null) {
            throw new NotFoundHttpException('Target not found in this workspace.');
        }

        // Resolve the source (branch or PR) and ensure it belongs to a
        // repo on one of the workspace's installations.
        $source = $this->resolveSource($workspace, $data['source_type'], (int) $data['source_id']);
        if ($source === null) {
            throw new NotFoundHttpException('GitHub source not found in this workspace.');
        }

        $link = GithubLink::query()->firstOrCreate(
            [
                'source_type' => $data['source_type'],
                'source_id' => (int) $data['source_id'],
                'linkable_type' => $linkable::class,
                'linkable_id' => $linkable->getKey(),
            ],
            [
                'linked_by_user_id' => $user->id,
                'auto' => false,
            ],
        );

        if ($link->wasRecentlyCreated) {
            $this->emitLinkedActivity($linkable, $data['source_type'], $source, $user->id);
        }

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $workspace = $this->workspace();

        $link = GithubLink::query()->find($id);
        if ($link === null) {
            return back();
        }

        // Resolve the linkable and confirm it belongs to the active
        // workspace. We don't restrict to the original linker — any
        // workspace member may unlink.
        $linkableClass = $link->linkable_type;
        if (! is_string($linkableClass) || ! class_exists($linkableClass)) {
            $link->delete();

            return back();
        }
        /** @var Model|null $linkable */
        $linkable = $linkableClass::query()
            ->where('id', $link->linkable_id)
            ->where('workspace_id', $workspace->id)
            ->first();
        if ($linkable === null) {
            throw new NotFoundHttpException('Link target not in this workspace.');
        }

        $link->delete();

        return back();
    }

    private function resolveSource(
        Workspace $workspace,
        string $type,
        int $id,
    ): GithubBranch|GithubPullRequest|null {
        $installationIds = GithubInstallation::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('id');
        if ($installationIds->isEmpty()) {
            return null;
        }

        if ($type === 'branch') {
            /** @var GithubBranch|null $row */
            $row = GithubBranch::query()
                ->whereHas('repo', static function ($q) use ($installationIds): void {
                    $q->whereIn('installation_id', $installationIds);
                })
                ->find($id);

            return $row;
        }

        /** @var GithubPullRequest|null $row */
        $row = GithubPullRequest::query()
            ->whereHas('repo', static function ($q) use ($installationIds): void {
                $q->whereIn('installation_id', $installationIds);
            })
            ->find($id);

        return $row;
    }

    private function emitLinkedActivity(
        Model $linkable,
        string $sourceType,
        GithubBranch|GithubPullRequest $source,
        int $userId,
    ): void {
        $kind = $sourceType === 'pull_request' ? 'pull_request_linked' : 'branch_linked';

        $payload = $sourceType === 'pull_request' && $source instanceof GithubPullRequest
            ? [
                'pr_number' => $source->number,
                'pr_title' => $source->title,
                'pr_url' => $source->html_url,
                'branch_name' => $source->head_branch_name,
            ]
            : [
                'branch_name' => $source instanceof GithubBranch ? $source->name : null,
            ];

        if ($linkable instanceof Issue) {
            IssueActivity::create([
                'issue_id' => $linkable->id,
                'actor_user_id' => $userId,
                'kind' => $kind,
                'payload' => $payload,
                'occurred_at' => now(),
            ]);

            return;
        }

        if ($linkable instanceof Project) {
            ProjectActivity::create([
                'project_id' => $linkable->id,
                'actor_user_id' => $userId,
                'kind' => $kind,
                'payload' => $payload,
                'occurred_at' => now(),
            ]);
        }
    }

    private function workspace(): Workspace
    {
        if (! app()->bound('current.workspace')) {
            throw new NotFoundHttpException('No active workspace.');
        }
        $workspace = app('current.workspace');
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        return $workspace;
    }
}
