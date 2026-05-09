<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw sink of every webhook GitHub sends us. Idempotent on delivery_id so
 * GitHub can re-deliver freely. Everything that derives state in aims
 * (branches, PRs, links, activity) is rebuildable from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_webhook_events')) {
            return;
        }

        Schema::create('github_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_id', 64)->unique();
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->string('installation_external_id', 32)->nullable();
            $table->string('event_type', 64);
            $table->string('action', 64)->nullable();
            $table->string('repository_full_name', 200)->nullable();
            $table->string('sender_login', 100)->nullable();
            $table->boolean('signature_ok')->default(true);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_error', 500)->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamps();

            $table->foreign('installation_id')
                ->references('id')->on('github_installations')
                ->nullOnDelete();
            $table->index(['event_type', 'received_at']);
            $table->index(['repository_full_name', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_webhook_events');
    }
};
