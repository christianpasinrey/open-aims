<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cycle_resources')) {
            return;
        }

        Schema::create('cycle_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['file', 'link']);
            $table->string('name', 200);
            $table->string('url', 1024)->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['cycle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_resources');
    }
};
