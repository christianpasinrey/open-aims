<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'priority')) {
                // repo-style priority: 0=none, 1=urgent, 2=high, 3=medium, 4=low
                $table->unsignedTinyInteger('priority')->default(0)->after('state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            if (Schema::hasColumn('projects', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }
};
