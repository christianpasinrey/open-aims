<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfill('issue_resources', 'issue_id', Issue::class);
        $this->backfill('project_resources', 'project_id', Project::class);
    }

    private function backfill(string $table, string $fk, string $modelClass): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->where('is_plan', true)->orderBy('id')->get();
        $morphType = (new $modelClass)->getMorphClass();

        foreach ($rows as $row) {
            $entityId = $row->{$fk};

            $exists = DB::table('plans')
                ->where('planable_type', $morphType)
                ->where('planable_id', $entityId)
                ->where('is_current', true)
                ->exists();
            if ($exists) {
                continue;
            }

            $content = '';
            if (Schema::hasTable('media')) {
                $media = DB::table('media')
                    ->where('model_type', $this->resourceClassFor($table))
                    ->where('model_id', $row->id)
                    ->where('collection_name', 'attachment')
                    ->first();

                if ($media !== null) {
                    $path = storage_path("app/public/{$media->id}/{$media->file_name}");
                    if (is_readable($path)) {
                        $content = (string) file_get_contents($path);
                    }
                }
            }

            $format = str_ends_with(strtolower((string) $row->name), '.html') ? 'html' : 'md';

            Plan::create([
                'planable_type' => $morphType,
                'planable_id' => $entityId,
                'format' => $format,
                'content' => $content,
                'libs' => null,
                'version' => 1,
                'is_current' => true,
                'created_by_user_id' => $row->created_by_user_id,
            ]);
        }
    }

    private function resourceClassFor(string $table): string
    {
        return $table === 'issue_resources'
            ? \App\Modules\Issues\Models\IssueResource::class
            : \App\Modules\Projects\Models\ProjectResource::class;
    }

    public function down(): void
    {
        // Backfill is one-way; current plans created here are left in place.
    }
};
