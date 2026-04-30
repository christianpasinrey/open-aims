<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('issues', 'git_branch_name')) {
            Schema::table('issues', function (Blueprint $table): void {
                $table->string('git_branch_name')->nullable()->after('description');
                $table->index('git_branch_name');
            });
        }

        $this->backfillFromrepo();
    }

    public function down(): void
    {
        if (Schema::hasColumn('issues', 'git_branch_name')) {
            Schema::table('issues', function (Blueprint $table): void {
                $table->dropIndex(['git_branch_name']);
                $table->dropColumn('git_branch_name');
            });
        }
    }

    /**
     * Best-effort backfill: read the bundled repo snapshot and copy
     * `gitBranchName` into the new column for every issue we can match by
     * (team_key, number).
     */
    private function backfillFromrepo(): void
    {
        $jsonPath = base_path('database/seed-data/repo/issues.json');
        if (! is_file($jsonPath)) {
            return;
        }

        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            return;
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return;
        }

        foreach ($data as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $externalId = (string) ($issue['id'] ?? '');
            $branch = $issue['gitBranchName'] ?? null;
            if ($externalId === '' || ! is_string($branch) || $branch === '') {
                continue;
            }
            if (preg_match('/^([A-Za-z]+)-(\d+)$/', $externalId, $m) !== 1) {
                continue;
            }
            $teamKey = strtoupper($m[1]);
            $number = (int) $m[2];

            $teamId = DB::table('teams')->where('key', $teamKey)->value('id');
            if ($teamId === null) {
                continue;
            }

            DB::table('issues')
                ->where('team_id', $teamId)
                ->where('number', $number)
                ->whereNull('git_branch_name')
                ->update(['git_branch_name' => $branch]);
        }
    }
};
