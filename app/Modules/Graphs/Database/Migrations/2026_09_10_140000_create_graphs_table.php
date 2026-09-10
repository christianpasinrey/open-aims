<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('graphs')) {
            return;
        }

        Schema::create('graphs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('title', 200);
            $table->text('summary');
            $table->string('stage', 16);
            $table->string('repo', 100);
            $table->string('ref', 191);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'title']);
            $table->index(['workspace_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graphs');
    }
};
