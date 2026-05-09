<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('issue_resources')) {
            return;
        }

        if (Schema::hasColumn('issue_resources', 'is_plan')) {
            return;
        }

        Schema::table('issue_resources', function (Blueprint $table): void {
            $table->boolean('is_plan')->default(false)->after('type');
            $table->index(['issue_id', 'is_plan']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('issue_resources')) {
            return;
        }

        if (! Schema::hasColumn('issue_resources', 'is_plan')) {
            return;
        }

        Schema::table('issue_resources', function (Blueprint $table): void {
            $table->dropIndex(['issue_id', 'is_plan']);
            $table->dropColumn('is_plan');
        });
    }
};
