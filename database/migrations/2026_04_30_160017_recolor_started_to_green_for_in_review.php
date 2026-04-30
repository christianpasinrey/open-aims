<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Earlier migration recolored the late "started" state purple to mirror
     * repo's "In Review", but Done already owns the brand purple. repo
     * actually uses green for In Review, so swap back: yellow → green.
     */
    public function up(): void
    {
        $teamIds = DB::table('workflow_states')
            ->where('type', 'started')
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
                $count === 1 => ['#10b981'],
                $count === 2 => ['#f2c94c', '#10b981'],
                default => ['#f2c94c', '#3b82f6', '#10b981'],
            };

            foreach ($started as $i => $s) {
                // Only fix rows still on the previous palette (purple/violet
                // or untouched gray). Custom user colors stay.
                if (! in_array($s->color, ['#a855f7', '#94a3b8'], true)) {
                    continue;
                }
                DB::table('workflow_states')
                    ->where('id', $s->id)
                    ->update(['color' => $palette[$i] ?? '#10b981']);
            }
        }
    }

    public function down(): void
    {
        // No rollback.
    }
};
