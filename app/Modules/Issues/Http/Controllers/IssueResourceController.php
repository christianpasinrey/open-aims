<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Issues\Models\IssueResource;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Add / remove resources on an issue. A resource is either an uploaded
 * document (Spatie media library, 'attachment' collection on
 * IssueResource) or a saved external link.
 */
final class IssueResourceController
{
    public function store(Request $request, string $identifier): RedirectResponse
    {
        $issue = $this->resolveIssue($identifier);

        $type = $request->input('type');
        if (! in_array($type, ['file', 'link'], true)) {
            abort(422, 'Resource type must be "file" or "link".');
        }

        if ($type === 'file') {
            $request->validate([
                'file' => 'required|file|max:25600', // 25 MB
                'name' => 'sometimes|string|max:200',
            ]);

            $upload = $request->file('file');
            // 'name' is editable; default to original filename.
            $displayName = trim((string) $request->input('name', '')) !== ''
                ? (string) $request->input('name')
                : $upload->getClientOriginalName();

            DB::transaction(function () use ($issue, $request, $upload, $displayName): void {
                $resource = IssueResource::create([
                    'issue_id' => $issue->id,
                    'type' => 'file',
                    'name' => $displayName,
                    'url' => null,
                    'created_by_user_id' => $request->user()?->id,
                ]);

                $resource->addMedia($upload->getRealPath())
                    ->usingFileName($upload->hashName())
                    ->usingName($upload->getClientOriginalName())
                    ->toMediaCollection('attachment', 'public');

                IssueActivity::create([
                    'issue_id' => $issue->id,
                    'actor_user_id' => $request->user()?->getKey(),
                    'kind' => 'resource_added',
                    'payload' => [
                        'resource_id' => $resource->id,
                        'resource_type' => 'file',
                        'resource_name' => $resource->name,
                    ],
                    'occurred_at' => now(),
                ]);
            });
        } else {
            $data = $request->validate([
                'name' => 'required|string|max:200',
                'url' => 'required|url|max:1024',
            ]);

            $resource = IssueResource::create([
                'issue_id' => $issue->id,
                'type' => 'link',
                'name' => $data['name'],
                'url' => $data['url'],
                'created_by_user_id' => $request->user()?->id,
            ]);

            IssueActivity::create([
                'issue_id' => $issue->id,
                'actor_user_id' => $request->user()?->getKey(),
                'kind' => 'resource_added',
                'payload' => [
                    'resource_id' => $resource->id,
                    'resource_type' => 'link',
                    'resource_name' => $resource->name,
                    'resource_url' => $resource->url,
                ],
                'occurred_at' => now(),
            ]);
        }

        return back();
    }

    public function destroy(Request $request, string $identifier, int $id): RedirectResponse
    {
        $issue = $this->resolveIssue($identifier);

        $resource = IssueResource::query()
            ->where('issue_id', $issue->id)
            ->where('id', $id)
            ->first();
        if ($resource === null) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $name = $resource->name;
        $type = $resource->type;
        $resource->delete();

        IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $request->user()?->getKey(),
            'kind' => 'resource_removed',
            'payload' => [
                'resource_type' => $type,
                'resource_name' => $name,
            ],
            'occurred_at' => now(),
        ]);

        return back();
    }

    private function resolveIssue(string $identifier): Issue
    {
        if (preg_match('/^([A-Za-z]+)-(\d+)$/', $identifier, $m) !== 1) {
            throw new NotFoundHttpException('Invalid issue identifier.');
        }

        $teamKey = strtoupper($m[1]);
        $number = (int) $m[2];

        $workspace = $this->workspace();

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
            ->first();
        if ($issue === null) {
            throw new NotFoundHttpException('Issue not found.');
        }

        return $issue;
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
