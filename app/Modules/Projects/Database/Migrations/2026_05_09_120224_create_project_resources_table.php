<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_resources')) {
            return;
        }

        Schema::create('project_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // 'file' rows have a Spatie media record attached via the
            // 'attachment' collection; 'link' rows just store the URL.
            $table->enum('type', ['file', 'link']);
            $table->string('name', 200);
            $table->string('url', 1024)->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_resources');
    }
};
