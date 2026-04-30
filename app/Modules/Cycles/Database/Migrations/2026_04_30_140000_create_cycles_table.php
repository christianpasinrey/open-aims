<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('number');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'number']);
            $table->index(['team_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycles');
    }
};
