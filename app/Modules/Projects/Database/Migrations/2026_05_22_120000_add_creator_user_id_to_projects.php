<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'creator_user_id')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('creator_user_id')
                ->nullable()
                ->after('lead_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('projects', 'creator_user_id')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('creator_user_id');
        });
    }
};
