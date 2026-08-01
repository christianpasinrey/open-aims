<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;

/**
 * Shared lookups for the Issues MCP tools.
 *
 * Every helper here is workspace-scoped on purpose: an MCP caller must never
 * be able to reach an issue, team or label outside the workspace resolved by
 * `ResolvesWorkspace::bindWorkspace()`.
 */
trait ResolvesIssueRefs
{
    /**
     * Resolve a "TEAMKEY-N" identifier to an issue inside the workspace.
     *
     * @return array{0: ?Issue, 1: ?string} [issue, error message]
     */
    private function findIssueByIdentifier(Workspace $workspace, string $identifier): array
    {
        $identifier = trim($identifier);

        if (preg_match('/^([A-Za-z]+)-(\d+)$/', $identifier, $matches) !== 1) {
            return [null, "Invalid issue identifier '{$identifier}'. Expected TEAMKEY-N (e.g. \"LAM-275\")."];
        }

        $key = strtoupper($matches[1]);

        $team = Team::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('key', $key)
            ->first();

        if ($team === null) {
            return [null, "Team '{$key}' not found in workspace '{$workspace->slug}'."];
        }

        $issue = Issue::query()
            ->where('team_id', $team->getKey())
            ->where('number', (int) $matches[2])
            ->with('team:id,key')
            ->first();

        if ($issue === null) {
            return [null, $key.'-'.$matches[2].' not found.'];
        }

        return [$issue, null];
    }

    /** "TEAMKEY-N" for an issue, using the issue's OWN team (not the caller's). */
    private function issueIdentifier(Issue $issue): string
    {
        $key = $issue->relationLoaded('team')
            ? ($issue->team?->key ?? '')
            : (string) (Team::query()->whereKey($issue->team_id)->value('key') ?? '');

        return $key.'-'.$issue->number;
    }

    /**
     * Map label names to the team's label ids, case-insensitively, and report
     * back exactly which names matched and which did not. The old behaviour
     * silently dropped unknown names through a `whereIn`, so a typo looked
     * like success.
     *
     * @param  array<int,string>  $names
     * @return array{ids: list<int>, applied: list<string>, unknown: list<string>}
     */
    private function resolveTeamLabels(int $teamId, array $names): array
    {
        $labels = Label::query()
            ->where('team_id', $teamId)
            ->get(['id', 'name']);

        /** @var array<string, array{id:int,name:string}> $byLower */
        $byLower = [];
        foreach ($labels as $label) {
            $byLower[mb_strtolower((string) $label->name)] = [
                'id' => (int) $label->id,
                'name' => (string) $label->name,
            ];
        }

        $ids = [];
        $applied = [];
        $unknown = [];

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $match = $byLower[mb_strtolower($name)] ?? null;
            if ($match === null) {
                $unknown[] = $name;

                continue;
            }

            if (! in_array($match['id'], $ids, true)) {
                $ids[] = $match['id'];
                $applied[] = $match['name'];
            }
        }

        return [
            'ids' => $ids,
            'applied' => $applied,
            'unknown' => array_values(array_unique($unknown)),
        ];
    }
}
