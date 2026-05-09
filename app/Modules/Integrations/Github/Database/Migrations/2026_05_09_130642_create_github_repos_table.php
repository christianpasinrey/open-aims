<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_repos')) {
            return;
        }

        Schema::create('github_repos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installation_id')
                ->constrained('github_installations')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('github_id')->unique();
            $table->string('node_id', 64)->nullable();
            $table->string('full_name', 200)->index();
            $table->string('default_branch', 200)->default('main');
            $table->string('language', 64)->nullable();
            $table->boolean('private')->default(true);
            $table->boolean('archived')->default(false);
            $table->string('html_url', 300)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamps();

            $table->index(['installation_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_repos');
    }
};
