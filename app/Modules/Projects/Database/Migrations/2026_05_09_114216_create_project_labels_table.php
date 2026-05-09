<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_labels')) {
            return;
        }

        Schema::create('project_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('labels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_labels');
    }
};
