<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('issue_resources')) {
            return;
        }

        Schema::create('issue_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
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

            $table->index(['issue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_resources');
    }
};
