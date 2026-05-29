<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_pending_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_batch_id')->constrained()->cascadeOnDelete();
            $table->text('html');
            $table->string('mention')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['telegram_batch_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_pending_events');
    }
};
