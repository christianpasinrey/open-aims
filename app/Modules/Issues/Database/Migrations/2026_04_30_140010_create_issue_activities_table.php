<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Open vocabulary so future kinds don't require a migration.
            // Initial set: created, status_changed, priority_changed,
            // assigned, unassigned, project_set, project_unset,
            // cycle_set, cycle_unset, label_added, label_removed,
            // relation_added, relation_removed, mentioned.
            $table->string('kind', 32);
            // Free-form payload: { from, to, target_issue_id, label_id,
            //   project_id, cycle_id, mentioned_user_ids, ... }
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['issue_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_activities');
    }
};
