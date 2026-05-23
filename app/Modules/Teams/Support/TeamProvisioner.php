<?php

declare(strict_types=1);

namespace App\Modules\Teams\Support;

use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;

final class TeamProvisioner
{
    /** @var list<array{name:string,type:string,color:string}> */
    private const DEFAULT_STATES = [
        ['name' => 'Triage', 'type' => 'triage', 'color' => '#94a3b8'],
        ['name' => 'Backlog', 'type' => 'backlog', 'color' => '#64748b'],
        ['name' => 'Todo', 'type' => 'unstarted', 'color' => '#475569'],
        ['name' => 'In Progress', 'type' => 'started', 'color' => '#facc15'],
        ['name' => 'Done', 'type' => 'completed', 'color' => '#10b981'],
        ['name' => 'Canceled', 'type' => 'canceled', 'color' => '#ef4444'],
    ];

    public function create(Workspace $workspace, string $name, ?string $key = null, ?string $color = null, ?string $icon = null): Team
    {
        $name = trim($name) !== '' ? trim($name) : 'Team';

        $finalKey = ($key !== null && $key !== '')
            ? $this->normalizeKey($key)
            : $this->uniqueKey($workspace, $this->generateKey($name));

        $team = Team::create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'key' => $finalKey,
            'color' => ($color !== null && $color !== '') ? $color : '#6366f1',
            'icon' => $icon,
            'issue_counter' => 0,
        ]);

        foreach (self::DEFAULT_STATES as $i => $s) {
            WorkflowState::create([
                'team_id' => $team->id,
                'name' => $s['name'],
                'type' => $s['type'],
                'color' => $s['color'],
                'position' => $i,
            ]);
        }

        return $team;
    }

    public function suggestKey(Workspace $workspace, string $name): string
    {
        return $this->uniqueKey($workspace, $this->generateKey($name));
    }

    private function normalizeKey(string $key): string
    {
        $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper($key)) ?? '';

        return $clean !== '' ? substr($clean, 0, 8) : 'TEAM';
    }

    private function generateKey(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) >= 2) {
            $base = '';
            foreach ($words as $w) {
                $base .= $w[0];
                if (strlen($base) >= 4) {
                    break;
                }
            }
        } else {
            $base = substr($words[0] ?? '', 0, 3);
        }

        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'TEAM';

        return substr($base, 0, 8);
    }

    private function uniqueKey(Workspace $workspace, string $base): string
    {
        $base = $base !== '' ? $base : 'TEAM';
        if (! $this->keyExists($workspace, $base)) {
            return $base;
        }
        for ($i = 2; $i < 1000; $i++) {
            $suffix = (string) $i;
            $candidate = substr($base, 0, max(1, 8 - strlen($suffix))).$suffix;
            if (! $this->keyExists($workspace, $candidate)) {
                return $candidate;
            }
        }

        return substr($base, 0, 4).substr((string) now()->timestamp, -4);
    }

    private function keyExists(Workspace $workspace, string $key): bool
    {
        return Team::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('key', $key)
            ->exists();
    }
}
