<?php

declare(strict_types=1);

use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Issues\Support\IssueActivityRecorder;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Support\ProjectActivityRecorder;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];
});

describe('IssueActivityRecorder (shared by controllers + MCP tools)', function () {
    it('logs a created activity', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);

        app(IssueActivityRecorder::class)->created($issue, $this->user->id);

        expect(IssueActivity::where('issue_id', $issue->id)->where('kind', 'created')->exists())->toBeTrue();
    });

    it('logs status_changed and assigned from a snapshot/record diff', function () {
        $recorder = app(IssueActivityRecorder::class);
        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);

        $snapshot = $recorder->snapshot($issue);

        $issue->fill([
            'workflow_state_id' => $this->states['In Progress']->id,
            'assignee_user_id' => $this->user->id,
        ])->save();

        $recorder->record($issue->fresh(['labels']), $snapshot['before'], $snapshot['labelIds'], $this->user->id);

        $kinds = IssueActivity::where('issue_id', $issue->id)->pluck('kind')->all();
        expect($kinds)->toContain('status_changed')
            ->and($kinds)->toContain('assigned');
    });
});

describe('ProjectActivityRecorder (shared by controllers + MCP tools)', function () {
    it('logs a created activity', function () {
        $project = Project::factory()->create(['workspace_id' => $this->workspace->id]);

        app(ProjectActivityRecorder::class)->created($project, $this->user->id);

        expect(ProjectActivity::where('project_id', $project->id)->where('kind', 'created')->exists())->toBeTrue();
    });

    it('logs state_changed from a snapshot/record diff', function () {
        $recorder = app(ProjectActivityRecorder::class);
        $project = Project::factory()->create([
            'workspace_id' => $this->workspace->id,
            'state' => 'started',
        ]);

        $snapshot = $recorder->snapshot($project);

        $project->fill(['state' => 'completed'])->save();

        $recorder->record($project->fresh(), $snapshot, $this->user->id);

        $activity = ProjectActivity::where('project_id', $project->id)->where('kind', 'state_changed')->first();
        expect($activity)->not->toBeNull()
            ->and($activity->payload['from'])->toBe('started')
            ->and($activity->payload['to'])->toBe('completed');
    });
});
