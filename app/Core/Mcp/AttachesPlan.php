<?php

declare(strict_types=1);

namespace App\Core\Mcp;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Attaches a plan document to any planable model (Issue, Project, ...).
 * At most one current plan per entity; previous plans are kept as history
 * (is_current=false) and versioned.
 */
trait AttachesPlan
{
    /**
     * @param  array<int,string>|null  $libs
     */
    private function attachPlan(Model $planable, string $content, string $format, ?array $libs, ?int $userId): Plan
    {
        $format = $format === 'html' ? 'html' : 'md';
        $type = $planable->getMorphClass();
        $id = $planable->getKey();

        return DB::transaction(function () use ($type, $id, $content, $format, $libs, $userId) {
            $nextVersion = (int) Plan::query()
                ->where('planable_type', $type)
                ->where('planable_id', $id)
                ->lockForUpdate()
                ->max('version') + 1;

            Plan::query()
                ->where('planable_type', $type)
                ->where('planable_id', $id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return Plan::create([
                'planable_type' => $type,
                'planable_id' => $id,
                'format' => $format,
                'content' => $content,
                'libs' => ! empty($libs) ? array_values($libs) : null,
                'version' => $nextVersion,
                'is_current' => true,
                'created_by_user_id' => $userId,
            ]);
        });
    }

    /**
     * @return array{id:int,format:string,libs:array<int,string>|null,version:int,content_preview:string,url:string,uploaded_at:?string}|null
     */
    private function planSummary(?Plan $plan): ?array
    {
        if ($plan === null) {
            return null;
        }

        return [
            'id' => (int) $plan->id,
            'format' => $plan->format,
            'libs' => $plan->libs,
            'version' => $plan->version,
            'content_preview' => mb_substr($plan->content, 0, 500),
            'url' => url("/plans/{$plan->id}/raw"),
            'uploaded_at' => $plan->created_at?->toIso8601String(),
        ];
    }

    /** Full plan body, capped for inline rendering (null if over the cap). */
    private function planFullContent(?Plan $plan, int $maxBytes = 200 * 1024): ?string
    {
        if ($plan === null || strlen($plan->content) > $maxBytes) {
            return null;
        }

        return $plan->content;
    }
}
