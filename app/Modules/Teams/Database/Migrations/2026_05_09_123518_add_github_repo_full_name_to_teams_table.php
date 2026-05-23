<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            if (! Schema::hasColumn('teams', 'github_repo_full_name')) {
                // owner/repo, e.g. "owner/repo"
                $table->string('github_repo_full_name', 200)->nullable()->after('private');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            if (Schema::hasColumn('teams', 'github_repo_full_name')) {
                $table->dropColumn('github_repo_full_name');
            }
        });
    }
};
