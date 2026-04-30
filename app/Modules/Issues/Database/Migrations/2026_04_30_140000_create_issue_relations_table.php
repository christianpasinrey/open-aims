<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('target_issue_id')->constrained('issues')->cascadeOnDelete();
            // 'blocks' from source to target ⇔ 'blocked_by' inverse;
            // 'related' is symmetric; 'duplicate' from source means
            // source duplicates target.
            $table->enum('type', ['blocks', 'related', 'duplicate']);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_issue_id', 'target_issue_id', 'type'], 'issue_relations_unique');
            $table->index(['target_issue_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_relations');
    }
};
