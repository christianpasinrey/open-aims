<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Issues module declares cycle_id as a plain unsignedBigInteger to avoid
 * a hard dependency on Cycles at migration time. We add the foreign key here
 * once the cycles table exists, keeping cross-module coupling at the DB layer
 * instead of inside the Issues migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->foreign('cycle_id')
                ->references('id')->on('cycles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropForeign(['cycle_id']);
        });
    }
};
