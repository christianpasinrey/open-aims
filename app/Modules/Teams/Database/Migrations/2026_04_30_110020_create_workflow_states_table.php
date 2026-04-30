<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['backlog', 'unstarted', 'started', 'completed', 'canceled', 'triage'])
                ->default('unstarted');
            $table->string('color', 9)->default('#94a3b8');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_states');
    }
};
