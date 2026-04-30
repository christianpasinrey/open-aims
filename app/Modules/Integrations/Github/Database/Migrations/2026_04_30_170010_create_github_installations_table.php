<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_installations')) {
            return;
        }

        Schema::create('github_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('installation_id')->unique();
            $table->string('account_login');
            $table->string('account_type')->default('Organization');
            $table->string('repository_selection')->default('all');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_installations');
    }
};
