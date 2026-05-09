<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_links')) {
            return;
        }

        Schema::create('github_links', function (Blueprint $table): void {
            $table->id();
            // 'branch' or 'pull_request' — kept short to fit in a small index.
            $table->string('source_type', 16);
            // FK by convention only (polymorphic source) — no real
            // foreign key because the column references one of two tables.
            $table->unsignedBigInteger('source_id');
            // Laravel morph map style — full class name or alias.
            $table->string('linkable_type', 80);
            $table->unsignedBigInteger('linkable_id');
            $table->foreignId('linked_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // True for matches written automatically by LinkPullRequestAction;
            // false for manual links from the picker UI.
            $table->boolean('auto')->default(true);
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'linkable_type', 'linkable_id'],
                'github_links_source_linkable_unique',
            );
            $table->index(['linkable_type', 'linkable_id'], 'github_links_linkable_idx');
            $table->index(['source_type', 'source_id'], 'github_links_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_links');
    }
};
