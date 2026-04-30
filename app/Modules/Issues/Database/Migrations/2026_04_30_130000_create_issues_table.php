<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('cycle_id')->nullable();
            $table->foreignId('parent_issue_id')->nullable()
                ->references('id')->on('issues')->nullOnDelete();

            $table->unsignedBigInteger('number');
            $table->string('title');
            $table->longText('description')->nullable();

            $table->foreignId('workflow_state_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('priority')->default(0);

            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();

            $table->float('estimate')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'number']);
            $table->index(['workspace_id', 'team_id', 'workflow_state_id']);
            $table->index(['workspace_id', 'assignee_user_id']);
            $table->index(['project_id']);
            $table->index(['cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
