<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope')->default('personal');
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->json('filters');
            $table->string('grouping')->default('status');
            $table->string('sorting')->default('priority');
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'owner_user_id']);
            $table->index(['scope', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_views');
    }
};
