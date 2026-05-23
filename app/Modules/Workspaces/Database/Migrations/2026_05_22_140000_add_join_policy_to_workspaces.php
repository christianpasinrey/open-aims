<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('workspaces', 'join_policy')) {
            return;
        }
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->enum('join_policy', ['open', 'request', 'private'])
                ->default('request')
                ->after('owner_user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workspaces', 'join_policy')) {
            return;
        }
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('join_policy');
        });
    }
};
