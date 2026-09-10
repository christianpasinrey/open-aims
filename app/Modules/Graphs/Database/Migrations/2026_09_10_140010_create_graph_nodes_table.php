<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('graph_nodes')) {
            return;
        }

        Schema::create('graph_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Keys can be longer than an index allows, so uniqueness lives on the hash.
            $table->string('key', 1024);
            $table->char('key_hash', 64);
            $table->string('kind', 16);
            $table->string('repo', 100);
            $table->foreignId('parent_node_id')->nullable()->constrained('graph_nodes')->nullOnDelete();
            $table->string('file_path', 512)->nullable();
            $table->string('language', 32)->nullable();
            $table->string('namespace')->nullable();
            $table->string('class_name')->nullable();
            $table->string('class_type', 16)->nullable();
            $table->string('function_name')->nullable();
            $table->string('signature', 1024)->nullable();
            $table->string('visibility', 16)->nullable();
            $table->string('resource_type', 32)->nullable();
            $table->string('resource_name')->nullable();
            $table->string('context', 16)->nullable();
            $table->string('role', 32)->nullable();
            $table->text('description')->nullable();
            $table->text('purpose')->nullable();
            $table->text('summary')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('last_referenced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'key_hash']);
            $table->index(['workspace_id', 'kind']);
            $table->index(['workspace_id', 'file_path']);
            $table->index(['workspace_id', 'namespace']);
            $table->index(['workspace_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_nodes');
    }
};
