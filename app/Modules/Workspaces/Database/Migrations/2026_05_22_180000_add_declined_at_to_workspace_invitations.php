<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('workspace_invitations', 'declined_at')) {
            return;
        }
        Schema::table('workspace_invitations', function (Blueprint $table): void {
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workspace_invitations', 'declined_at')) {
            return;
        }
        Schema::table('workspace_invitations', function (Blueprint $table): void {
            $table->dropColumn('declined_at');
        });
    }
};
