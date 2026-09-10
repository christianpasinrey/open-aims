<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('graph_references')) {
            return;
        }

        Schema::create('graph_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('graph_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graph_node_id')->constrained()->cascadeOnDelete();
            $table->string('context', 16)->nullable();
            $table->string('role', 32)->nullable();
            $table->string('change', 16);
            $table->text('description')->nullable();
            $table->text('purpose')->nullable();
            $table->text('summary')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['graph_id', 'graph_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_references');
    }
};
