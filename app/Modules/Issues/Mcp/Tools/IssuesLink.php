<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\IssueRelation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Create or remove a relation (graph edge) between two issues in the same '
    .'workspace. DIRECTION MATTERS: type="blocks" with from=A, to=B means '
    .'"A blocks B" — read from B the same edge appears as `blocked_by` in issues-get. '
    .'type="related" is symmetric (one edge, visible from both sides). '
    .'type="duplicate" with from=A, to=B means "A duplicates B" and appears as '
    .'`duplicate_of` on A. Pass remove=true to delete an existing edge instead of '
    .'creating one. Self-links and duplicate edges are rejected. '
    .'This tool does NOT touch hierarchy — set an epic/sub-issue relationship with '
    .'the `parent` field on issues-create / issues-update. '
    .'Returns the full relation graph of the `from` issue after the change.'
)]
class IssuesLink extends Tool
{
    use ReadsIssueGraph;
    use ResolvesIssueRefs;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $user = auth()->user();
        if ($user === null) {
            return Response::error('Unauthenticated.');
        }

        $data = Validator::make($request->all(), [
            'from' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
            'to' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
            'type' => 'required|string|in:blocks,related,duplicate',
            'remove' => 'nullable|boolean',
        ])->validate();

        [$from, $fromError] = $this->findIssueByIdentifier($workspace, $data['from']);
        if ($from === null) {
            return Response::error($fromError ?? 'Source issue not found.');
        }

        [$to, $toError] = $this->findIssueByIdentifier($workspace, $data['to']);
        if ($to === null) {
            return Response::error($toError ?? 'Target issue not found.');
        }

        if ($from->getKey() === $to->getKey()) {
            return Response::error(
                'Cannot link an issue to itself ('.$this->issueIdentifier($from).').'
            );
        }

        $type = $data['type'];
        $remove = (bool) ($data['remove'] ?? false);

        // `related` is symmetric: a single row serves both directions, so both
        // the duplicate check and the removal must look at the reverse row too.
        $symmetric = $type === 'related';

        $existing = IssueRelation::query()
            ->where('type', $type)
            ->where(function ($q) use ($from, $to, $symmetric): void {
                $q->where(fn ($w) => $w
                    ->where('source_issue_id', $from->getKey())
                    ->where('target_issue_id', $to->getKey()));

                if ($symmetric) {
                    $q->orWhere(fn ($w) => $w
                        ->where('source_issue_id', $to->getKey())
                        ->where('target_issue_id', $from->getKey()));
                }
            })
            ->get();

        if ($remove) {
            if ($existing->isEmpty()) {
                return Response::error(sprintf(
                    "No '%s' link exists from %s to %s.",
                    $type,
                    $this->issueIdentifier($from),
                    $this->issueIdentifier($to),
                ));
            }

            IssueRelation::query()->whereKey($existing->modelKeys())->delete();
            $action = 'removed';
        } else {
            if ($existing->isNotEmpty()) {
                return Response::error(sprintf(
                    "A '%s' link between %s and %s already exists.",
                    $type,
                    $this->issueIdentifier($from),
                    $this->issueIdentifier($to),
                ));
            }

            IssueRelation::create([
                'source_issue_id' => $from->getKey(),
                'target_issue_id' => $to->getKey(),
                'type' => $type,
                'created_by_user_id' => (int) $user->getAuthIdentifier(),
            ]);
            $action = 'created';
        }

        return Response::json([
            'action' => $action,
            'from' => $this->issueIdentifier($from),
            'to' => $this->issueIdentifier($to),
            'type' => $type,
            'relations' => $this->issueGraph($from->refresh()),
            'url' => '/issues/'.$this->issueIdentifier($from),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->required()->description(
                'Source issue identifier (e.g. "LAM-275"). For type="blocks" this is the BLOCKING issue.'
            ),
            'to' => $schema->string()->required()->description(
                'Target issue identifier (e.g. "LAM-280"). For type="blocks" this is the BLOCKED issue.'
            ),
            'type' => $schema->string()->required()->description(
                '"blocks" (from blocks to), "related" (symmetric), or "duplicate" (from duplicates to).'
            ),
            'remove' => $schema->boolean()->description(
                'Set true to delete the edge instead of creating it (default false).'
            ),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — '
                .'when omitted the FIRST membership by id is used, which may not be the one '
                .'the user means. Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
