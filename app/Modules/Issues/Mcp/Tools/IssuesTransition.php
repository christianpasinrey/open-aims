<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Support\IssueActivityRecorder;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Convenience over issues.update — transition an issue to a state by '
    .'name (case-insensitive). Auto-sets started_at/completed_at/canceled_at.'
)]
class IssuesTransition extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }

        $data = Validator::make($request->all(), [
            'identifier' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
            'to' => 'required|string|max:64',
        ])->validate();

        [$key, $number] = explode('-', strtoupper($data['identifier']));
        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', $key)
            ->first();
        if ($team === null) {
            return Response::error("Team '{$key}' not found.");
        }

        $newState = WorkflowState::query()
            ->where('team_id', $team->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($data['to'])])
            ->first();
        if ($newState === null) {
            $available = WorkflowState::query()
                ->where('team_id', $team->id)
                ->orderBy('position')
                ->pluck('name')
                ->all();

            return Response::error("State '{$data['to']}' not found. Available: ".implode(', ', $available));
        }

        $issue = Issue::query()
            ->where('team_id', $team->id)
            ->where('number', (int) $number)
            ->first();
        if ($issue === null) {
            return Response::error("Issue {$data['identifier']} not found.");
        }

        $changes = ['workflow_state_id' => $newState->id];
        if ($newState->type === 'started' && $issue->started_at === null) {
            $changes['started_at'] = now();
        } elseif ($newState->type === 'completed') {
            $changes['completed_at'] = now();
        } elseif ($newState->type === 'canceled') {
            $changes['canceled_at'] = now();
        }

        $recorder = app(IssueActivityRecorder::class);
        $snapshot = $recorder->snapshot($issue);

        $issue->fill($changes)->save();

        $recorder->record(
            $issue->fresh(['labels']),
            $snapshot['before'],
            $snapshot['labelIds'],
            auth()->id(),
        );

        return Response::json([
            'identifier' => $team->key.'-'.$issue->number,
            'state' => $newState->name,
            'state_type' => $newState->type,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required()->description('Issue id (e.g. "LAM-275").'),
            'to' => $schema->string()->required()->description('Target state name (e.g. "Todo", "In Progress", "Done").'),
            'workspace_slug' => $schema->string(),
        ];
    }
}
