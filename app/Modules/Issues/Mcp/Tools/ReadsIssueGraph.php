<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueRelation;

/**
 * Reads the issue relation graph in the same groupings the issue detail UI
 * uses (see IssueDetailController::loadRelations), so MCP callers and the web
 * app never disagree about what "blocked_by" means.
 *
 * Direction matters: a row (source=A, target=B, type=blocks) means A blocks B.
 * Read from B, the very same row is "blocked_by A". `related` is symmetric and
 * is therefore merged from both directions. `duplicate` from the source means
 * "this issue duplicates the target", hence `duplicate_of`.
 */
trait ReadsIssueGraph
{
    /**
     * @return array{
     *   blocks: list<array{identifier:string,title:string,state:?string}>,
     *   blocked_by: list<array{identifier:string,title:string,state:?string}>,
     *   related: list<array{identifier:string,title:string,state:?string}>,
     *   duplicate_of: list<array{identifier:string,title:string,state:?string}>,
     * }
     */
    private function issueGraph(Issue $issue): array
    {
        $outgoing = IssueRelation::query()
            ->where('source_issue_id', $issue->getKey())
            ->with(['target:id,team_id,number,title,workflow_state_id', 'target.workflowState:id,name', 'target.team:id,key'])
            ->get();

        $incoming = IssueRelation::query()
            ->where('target_issue_id', $issue->getKey())
            ->with(['source:id,team_id,number,title,workflow_state_id', 'source.workflowState:id,name', 'source.team:id,key'])
            ->get();

        $shape = static fn (?Issue $other): ?array => $other === null ? null : [
            'identifier' => ($other->team?->key ?? '').'-'.$other->number,
            'title' => $other->title,
            'state' => $other->workflowState?->name,
        ];

        $out = static fn ($rows, string $type) => $rows
            ->where('type', $type)
            ->map(fn (IssueRelation $r): ?array => $shape($r->target))
            ->filter()->values();

        $in = static fn ($rows, string $type) => $rows
            ->where('type', $type)
            ->map(fn (IssueRelation $r): ?array => $shape($r->source))
            ->filter()->values();

        return [
            'blocks' => $out($outgoing, 'blocks')->all(),
            'blocked_by' => $in($incoming, 'blocks')->all(),
            'related' => $out($outgoing, 'related')
                ->concat($in($incoming, 'related'))
                ->unique('identifier')
                ->values()
                ->all(),
            'duplicate_of' => $out($outgoing, 'duplicate')->all(),
        ];
    }
}
