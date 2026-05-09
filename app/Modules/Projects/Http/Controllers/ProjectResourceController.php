<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Models\ProjectResource;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Add / remove resources on a project. A resource is either an uploaded
 * document (Spatie media library, 'attachment' collection on
 * ProjectResource) or a saved external link.
 */
final class ProjectResourceController
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);

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

            DB::transaction(function () use ($project, $request, $upload, $displayName): void {
                $resource = ProjectResource::create([
                    'project_id' => $project->id,
                    'type' => 'file',
                    'name' => $displayName,
                    'url' => null,
                    'created_by_user_id' => $request->user()?->id,
                ]);

                $resource->addMedia($upload->getRealPath())
                    ->usingFileName($upload->hashName())
                    ->usingName($upload->getClientOriginalName())
                    ->toMediaCollection('attachment', 'public');

                ProjectActivity::create([
                    'project_id' => $project->id,
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

            $resource = ProjectResource::create([
                'project_id' => $project->id,
                'type' => 'link',
                'name' => $data['name'],
                'url' => $data['url'],
                'created_by_user_id' => $request->user()?->id,
            ]);

            ProjectActivity::create([
                'project_id' => $project->id,
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

    public function destroy(Request $request, string $slug, int $id): RedirectResponse
    {
        $project = $this->resolveProject($slug);

        $resource = ProjectResource::query()
            ->where('project_id', $project->id)
            ->where('id', $id)
            ->first();
        if ($resource === null) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $name = $resource->name;
        $type = $resource->type;
        $resource->delete();

        ProjectActivity::create([
            'project_id' => $project->id,
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
