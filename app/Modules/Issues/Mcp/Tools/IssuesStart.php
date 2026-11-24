<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Support\IssueActivityRecorder;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Pick up an issue in one call. If it is not started yet: assigns it to the caller and moves it to '
    .'the team\'s first started state. Always returns the `branch` to work on (stored on the issue so a '
    .'pull request from that branch links itself) and a `brief`: title, description with its '
    .'acceptance criteria, branch and URL, ready to hand to a coding agent. Safe to repeat: an issue '
    .'already started, completed or canceled keeps its state and assignee (`changed: false`).'
)]
class IssuesStart extends Tool
{
    use PresentsBoardIssues;
    use ResolvesIssueRefs;
    use ResolvesWorkspace;

    /** State types an issue can still be started from. */
    private const STARTABLE = ['triage', 'backlog', 'unstarted'];

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
            'identifier' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
        ])->validate();

        [$found, $error] = $this->findIssueByIdentifier($workspace, $data['identifier']);
        if ($found === null) {
            return Response::error((string) $error);
        }

        $issue = Issue::query()->with(['team:id,key,color', 'workflowState'])->findOrFail($found->getKey());
        $startable = in_array($issue->workflowState?->type, self::STARTABLE, true);

        $started = null;
        if ($startable) {
            $started = WorkflowState::query()
                ->where('team_id', $issue->team_id)
                ->where('type', 'started')
                ->orderBy('position')
                ->first();

            if ($started === null) {
                return Response::error("Team '{$issue->team?->key}' has no started state.");
            }
        }

        DB::transaction(function () use ($issue, $started, $user): void {
            // A stored branch is what GitHub links pull requests by; keep it.
            if (trim((string) $issue->git_branch_name) === '') {
                $issue->git_branch_name = $this->issueBranchName($issue);
            }

            if ($started === null) {
                $issue->save();

                return;
            }

            $recorder = app(IssueActivityRecorder::class);
            $snapshot = $recorder->snapshot($issue);

            $issue->workflow_state_id = $started->id;
            $issue->assignee_user_id = $user->getAuthIdentifier();
            $issue->started_at ??= now();
            $issue->save();

            $recorder->record(
                $issue->fresh(['labels']),
                $snapshot['before'],
                $snapshot['labelIds'],
                (int) $user->getAuthIdentifier(),
            );
        });

        $issue = Issue::query()->with($this->boardIssueRelations())->findOrFail($issue->getKey());
        $row = $this->boardIssueRow($issue);
        $branch = $this->issueBranchName($issue);

        return Response::json([
            'identifier' => $row['identifier'],
            'changed' => $started !== null,
            'state' => $row['state'],
            'assignee' => $row['assignee'],
            'branch' => $branch,
            'url' => $row['url'],
            'brief' => $this->brief($issue, $row['identifier'], $branch, $row['url']),
        ]);
    }

    /** The first message for an agent that will work the issue. */
    private function brief(Issue $issue, string $identifier, string $branch, string $url): string
    {
        $description = trim((string) $issue->description);

        return "# {$identifier}: {$issue->title}\n\n"
            .($description === '' ? '' : $description."\n\n")
            ."Branch: {$branch}\n"
            ."Issue: {$url}";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required()->description('Issue id (e.g. "LAM-275").'),
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
