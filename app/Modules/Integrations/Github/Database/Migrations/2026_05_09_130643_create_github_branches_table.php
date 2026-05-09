<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_branches')) {
            return;
        }

        Schema::create('github_branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repo_id')->constrained('github_repos')->cascadeOnDelete();
            $table->string('name', 300);
            $table->string('head_sha', 64)->nullable();
            $table->string('last_pusher_login', 100)->nullable();
            $table->timestamp('last_pushed_at')->nullable();
            // Set when GitHub fires `delete` for the ref. We keep the row so
            // history (PRs that targeted this branch, links to issues) stays
            // queryable.
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['repo_id', 'name']);
            $table->index(['repo_id', 'last_pushed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_branches');
    }
};
