<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_linked_pull_requests')) {
            return;
        }

        Schema::create('github_linked_pull_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('installation_id')
                ->constrained('github_installations')
                ->cascadeOnDelete();

            $table->unsignedInteger('pr_number');
            $table->string('pr_node_id')->nullable();
            $table->string('pr_title');
            $table->string('pr_state')->default('open'); // open|closed|merged
            $table->string('pr_url');
            $table->string('branch_name');
            $table->string('author_login')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->unique(['installation_id', 'pr_node_id'], 'gh_links_install_node_unique');
            $table->index('issue_id');
            $table->index(['installation_id', 'pr_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_linked_pull_requests');
    }
};
