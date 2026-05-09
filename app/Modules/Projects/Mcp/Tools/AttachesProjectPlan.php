<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectResource;

/**
 * Shared helper for MCP tools that attach a plan document to a project.
 *
 * Plans are stored as ProjectResource rows with is_plan=true and the file
 * body persisted via Spatie media library on the 'attachment' collection.
 *
 * Convention: at most one *active* plan per project. When a new plan is
 * attached, every previous plan row for that project is flipped to
 * is_plan=false (history is preserved — rows are NOT deleted).
 */
trait AttachesProjectPlan
{
    /**
     * Attach a plan document to a project.
     *
     * @param  Project  $project  Target project.
     * @param  string  $content  Full markdown or HTML body.
     * @param  string  $format  'md' or 'html'.
     * @param  int|null  $userId  Author of the plan (auth()->id()).
     */
    private function attachPlanToProject(
        Project $project,
        string $content,
        string $format,
        ?int $userId,
    ): ProjectResource {
        $format = $format === 'html' ? 'html' : 'md';
        $extension = $format;
        $stamp = now()->format('YmdHis');
        $displayName = "plan.{$extension}";
        $storedName = "plan-{$stamp}.{$extension}";

        // Demote previous plan rows so at most one row carries is_plan=true.
        ProjectResource::query()
            ->where('project_id', $project->id)
            ->where('is_plan', true)
            ->update(['is_plan' => false]);

        $resource = ProjectResource::create([
            'project_id' => $project->id,
            'type' => 'file',
            'is_plan' => true,
            'name' => $displayName,
            'url' => null,
            'created_by_user_id' => $userId,
        ]);

        $resource
            ->addMediaFromString($content)
            ->usingFileName($storedName)
            ->usingName($displayName)
            ->toMediaCollection('attachment', 'public');

        return $resource->refresh();
    }

    /**
     * Build the response payload describing a plan resource.
     *
     * @return array{id:int,format:string,name:string,url:string|null,content_preview:string,uploaded_at:?string}|null
     */
    private function planSummary(?ProjectResource $resource): ?array
    {
        if ($resource === null) {
            return null;
        }

        $media = $resource->getFirstMedia('attachment');
        $name = $resource->name;
        $format = str_ends_with(strtolower($name), '.html') ? 'html' : 'md';

        $preview = '';
        if ($media !== null) {
            $path = $media->getPath();
            if (is_string($path) && $path !== '' && is_readable($path)) {
                $body = (string) @file_get_contents($path);
                $preview = mb_substr($body, 0, 500);
            }
        }

        return [
            'id' => (int) $resource->id,
            'format' => $format,
            'name' => $name,
            'url' => $media?->getFullUrl(),
            'content_preview' => $preview,
            'uploaded_at' => $resource->created_at?->toIso8601String(),
        ];
    }

    /**
     * Read the full plan content from disk (or null on failure / no media).
     */
    private function planFullContent(?ProjectResource $resource): ?string
    {
        if ($resource === null) {
            return null;
        }
        $media = $resource->getFirstMedia('attachment');
        if ($media === null) {
            return null;
        }
        $path = $media->getPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        return (string) @file_get_contents($path);
    }
}
