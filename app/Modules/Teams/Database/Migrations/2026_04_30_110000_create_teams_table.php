<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key', 8);
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 9)->default('#6366f1');
            $table->unsignedBigInteger('issue_counter')->default(0);
            $table->boolean('private')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'key']);
            $table->index(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
