<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('graph_edges')) {
            return;
        }

        Schema::create('graph_edges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('graph_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_node_id')->constrained('graph_nodes')->cascadeOnDelete();
            $table->foreignId('target_node_id')->constrained('graph_nodes')->cascadeOnDelete();
            $table->string('relation', 24);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['graph_id', 'source_node_id', 'target_node_id', 'relation'], 'graph_edges_unique');
            $table->index(['source_node_id', 'relation']);
            $table->index(['target_node_id', 'relation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_edges');
    }
};
