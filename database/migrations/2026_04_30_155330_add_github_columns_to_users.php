<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_id')->nullable()->after('email_verified_at');
            $table->string('github_login')->nullable()->after('github_id');
            $table->string('github_avatar_url', 500)->nullable()->after('github_login');
            $table->index('github_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['github_id']);
            $table->dropColumn(['github_id', 'github_login', 'github_avatar_url']);
        });
    }
};
