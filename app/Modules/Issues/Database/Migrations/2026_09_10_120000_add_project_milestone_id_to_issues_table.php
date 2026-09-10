<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an issue to one milestone of its project. Every step checks what
 * already exists, so a run that failed halfway can simply be re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('issues') || ! Schema::hasTable('project_milestones')) {
            return;
        }

        if (! Schema::hasColumn('issues', 'project_milestone_id')) {
            Schema::table('issues', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_milestone_id')->nullable()->after('project_id');
            });
        }

        if (! Schema::hasIndex('issues', ['project_milestone_id'])) {
            Schema::table('issues', function (Blueprint $table): void {
                $table->index('project_milestone_id');
            });
        }

        if (! $this->hasMilestoneForeignKey()) {
            Schema::table('issues', function (Blueprint $table): void {
                $table->foreign('project_milestone_id')
                    ->references('id')->on('project_milestones')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('issues')) {
            return;
        }

        if ($this->hasMilestoneForeignKey()) {
            Schema::table('issues', function (Blueprint $table): void {
                $table->dropForeign(['project_milestone_id']);
            });
        }

        if (Schema::hasIndex('issues', ['project_milestone_id'])) {
            Schema::table('issues', function (Blueprint $table): void {
                $table->dropIndex(['project_milestone_id']);
            });
        }

        if (Schema::hasColumn('issues', 'project_milestone_id')) {
            Schema::table('issues', function (Blueprint $table): void {
                $table->dropColumn('project_milestone_id');
            });
        }
    }

    private function hasMilestoneForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('issues'))
            ->contains(fn (array $fk): bool => $fk['columns'] === ['project_milestone_id']);
    }
};
