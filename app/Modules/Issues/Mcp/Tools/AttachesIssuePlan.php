<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueResource;

/**
 * Shared helper for MCP tools that attach a plan document to an issue.
 *
 * Plans are stored as IssueResource rows with is_plan=true and the file
 * body persisted via Spatie media library on the 'attachment' collection.
 *
 * Convention: at most one *active* plan per issue. When a new plan is
 * attached, every previous plan row for that issue is flipped to
 * is_plan=false (history is preserved — rows are NOT deleted).
 */
trait AttachesIssuePlan
{
    /**
     * Attach a plan document to an issue.
     *
     * @param  Issue  $issue  Target issue.
     * @param  string  $content  Full markdown or HTML body.
     * @param  string  $format  'md' or 'html'.
     * @param  int|null  $userId  Author of the plan (auth()->id()).
     */
    private function attachPlanToIssue(
        Issue $issue,
        string $content,
        string $format,
        ?int $userId,
    ): IssueResource {
        $format = $format === 'html' ? 'html' : 'md';
        $extension = $format;
        $stamp = now()->format('YmdHis');
        $displayName = "plan.{$extension}";
        $storedName = "plan-{$stamp}.{$extension}";

        // Demote previous plan rows so at most one row carries is_plan=true.
        IssueResource::query()
            ->where('issue_id', $issue->id)
            ->where('is_plan', true)
            ->update(['is_plan' => false]);

        $resource = IssueResource::create([
            'issue_id' => $issue->id,
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
    private function planSummary(?IssueResource $resource): ?array
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
    private function planFullContent(?IssueResource $resource): ?string
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
}
