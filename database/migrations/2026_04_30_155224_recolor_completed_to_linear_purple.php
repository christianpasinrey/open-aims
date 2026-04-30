<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Earlier backfill set Done (completed) and the last started state to
     * green. repo's defaults are purple for Done and a violet for late
     * started states. Convert the previously-applied greens.
     */
    public function up(): void
    {
        DB::table('workflow_states')
            ->where('type', 'completed')
            ->where('color', '#10b981')
            ->update(['color' => '#5e6ad2']);

        // Re-walk started states per team and reapply the new palette to
        // any rows still on the green that the old backfill produced.
        $teamIds = DB::table('workflow_states')
            ->where('type', 'started')
            ->where('color', '#10b981')
            ->pluck('team_id')
            ->unique();

        foreach ($teamIds as $teamId) {
            $started = DB::table('workflow_states')
                ->where('team_id', $teamId)
                ->where('type', 'started')
                ->orderBy('position')
                ->get(['id', 'color']);

            $count = $started->count();
            $palette = match (true) {
                $count === 1 => ['#f2c94c'],
                $count === 2 => ['#f2c94c', '#a855f7'],
                default => ['#f2c94c', '#3b82f6', '#a855f7'],
            };

            foreach ($started as $i => $s) {
                if ($s->color !== '#10b981') {
                    continue;
                }
                DB::table('workflow_states')
                    ->where('id', $s->id)
                    ->update(['color' => $palette[$i] ?? '#a855f7']);
            }
        }
    }

    public function down(): void
    {
        // No rollback — colors are not safe to revert without losing user edits.
    }
};
