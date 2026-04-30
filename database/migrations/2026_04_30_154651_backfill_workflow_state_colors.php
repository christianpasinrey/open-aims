<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * repo's snapshot doesn't ship colors for workflow states; the importer
     * defaulted everything to a single gray (#94a3b8). Replace with repo-like
     * defaults so the StatusIcon renders the right colour per state type, and
     * within `started` so "In Review" reads 3/4-green instead of grey.
     */
    public function up(): void
    {
        $defaults = [
            'triage' => '#fb923c',
            'backlog' => '#bec2c8',
            'unstarted' => '#95a2b3',
            // repo's Done is brand purple, not green.
            'completed' => '#5e6ad2',
            'canceled' => '#95a2b3',
        ];

        foreach ($defaults as $type => $color) {
            DB::table('workflow_states')
                ->where('type', $type)
                ->where('color', '#94a3b8')
                ->update(['color' => $color]);
        }

        // started: yellow -> blue -> green by rank within the team's started set.
        $teamIds = DB::table('workflow_states')->pluck('team_id')->unique();
        foreach ($teamIds as $teamId) {
            $started = DB::table('workflow_states')
                ->where('team_id', $teamId)
                ->where('type', 'started')
                ->where('color', '#94a3b8')
                ->orderBy('position')
                ->get(['id']);

            $count = $started->count();
            if ($count === 0) {
                continue;
            }

            // started gradient: yellow (early) → green (late, "In Review").
            // Done is purple (repo's brand) so green here doesn't clash.
            $palette = match (true) {
                $count === 1 => ['#10b981'],
                $count === 2 => ['#f2c94c', '#10b981'],
                default => ['#f2c94c', '#3b82f6', '#10b981'],
            };

            foreach ($started as $i => $s) {
                $color = $palette[$i] ?? '#10b981';
                DB::table('workflow_states')
                    ->where('id', $s->id)
                    ->update(['color' => $color]);
            }
        }
    }

    public function down(): void
    {
        // No rollback — colors are not safe to revert without losing user edits.
    }
};
