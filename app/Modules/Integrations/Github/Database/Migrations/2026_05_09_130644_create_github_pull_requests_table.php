<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_pull_requests')) {
            return;
        }

        Schema::create('github_pull_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repo_id')->constrained('github_repos')->cascadeOnDelete();
            $table->unsignedBigInteger('github_id')->unique();
            $table->string('node_id', 64)->nullable();
            $table->unsignedInteger('number');
            $table->string('title', 500);
            $table->mediumText('body')->nullable();
            $table->string('state', 16)->default('open'); // open, closed
            $table->boolean('merged')->default(false);
            $table->boolean('draft')->default(false);
            $table->string('author_login', 100)->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            // Snapshot of the head/base refs. We also store a FK to
            // github_branches when we have one, but the strings are cheap
            // and let us render even when branch rows are missing.
            $table->string('head_branch_name', 300)->nullable();
            $table->foreignId('head_branch_id')
                ->nullable()
                ->constrained('github_branches')
                ->nullOnDelete();
            $table->string('head_sha', 64)->nullable();
            $table->string('base_ref', 300)->nullable();
            $table->string('merge_commit_sha', 64)->nullable();
            $table->unsignedInteger('additions')->default(0);
            $table->unsignedInteger('deletions')->default(0);
            $table->unsignedInteger('changed_files')->default(0);
            $table->unsignedInteger('commits_count')->default(0);
            $table->string('html_url', 300)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->unique(['repo_id', 'number']);
            $table->index(['repo_id', 'state']);
            $table->index(['repo_id', 'merged']);
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_pull_requests');
    }
};
