<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plans')) {
            return;
        }

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->morphs('planable'); // planable_type, planable_id (+ index)
            $table->enum('format', ['md', 'html'])->default('md');
            $table->longText('content');
            $table->json('libs')->nullable(); // ["mermaid","chart"]
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['planable_type', 'planable_id', 'is_current'], 'plans_planable_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
