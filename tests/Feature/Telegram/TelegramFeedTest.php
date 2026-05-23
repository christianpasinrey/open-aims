<?php

declare(strict_types=1);

use App\Jobs\SendTelegramMessage;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Issues\Support\CommentTelegramFormatter;
use App\Modules\Issues\Support\IssueActivityTelegramFormatter;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Support\ProjectActivityRecorder;
use App\Modules\Projects\Support\ProjectActivityTelegramFormatter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->workspace = $fix['workspace'];
    $this->team = $fix['team'];
    $this->states = $fix['states'];

    config([
        'services.telegram.token' => 'TESTTOKEN',
        'services.telegram.channel' => '-1001234567890',
    ]);

    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
});

describe('formatters', function () {
    it('formats an issue status change with from -> to and a link', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);

        $activity = IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $this->user->id,
            'kind' => 'status_changed',
            'payload' => ['from' => ['name' => 'Todo'], 'to' => ['name' => 'Done']],
            'occurred_at' => now(),
        ]);

        $text = IssueActivityTelegramFormatter::format($activity);

        expect($text)->toContain('ENG-'.$issue->number)
            ->and($text)->toContain('Estado:')
            ->and($text)->toContain('<code>Todo</code>')
            ->and($text)->toContain('<code>Done</code>')
            ->and($text)->toContain('/issues/ENG-'.$issue->number);
    });

    it('escapes HTML in titles', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo'], [
            'title' => 'Bug <script> & "quotes"',
        ]);

        $activity = IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $this->user->id,
            'kind' => 'created',
            'payload' => null,
            'occurred_at' => now(),
        ]);

        $text = IssueActivityTelegramFormatter::format($activity);

        expect($text)->toContain('&lt;script&gt;')
            ->and($text)->not->toContain('<script>');
    });

    it('formats a project state change with Spanish labels', function () {
        $project = Project::factory()->create(['workspace_id' => $this->workspace->id]);

        $activity = ProjectActivity::create([
            'project_id' => $project->id,
            'actor_user_id' => $this->user->id,
            'kind' => 'state_changed',
            'payload' => ['from' => 'started', 'to' => 'completed'],
            'occurred_at' => now(),
        ]);

        $text = ProjectActivityTelegramFormatter::format($activity);

        expect($text)->toContain('<code>En curso</code>')
            ->and($text)->toContain('<code>Completado</code>');
    });
});

describe('SendTelegramMessage job', function () {
    it('posts the message to the telegram sendMessage endpoint', function () {
        (new SendTelegramMessage('<b>hola</b>'))->handle();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/botTESTTOKEN/sendMessage')
                && $request['chat_id'] === '-1001234567890'
                && $request['text'] === '<b>hola</b>'
                && $request['parse_mode'] === 'HTML';
        });
    });

    it('is a no-op when telegram is not configured', function () {
        config(['services.telegram.token' => null]);

        (new SendTelegramMessage('<b>hola</b>'))->handle();

        Http::assertNothingSent();
    });
});

describe('dispatch on activity', function () {
    it('dispatches a telegram job when an issue activity is recorded', function () {
        Bus::fake();

        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);
        IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $this->user->id,
            'kind' => 'created',
            'payload' => null,
            'occurred_at' => now(),
        ]);

        Bus::assertDispatched(SendTelegramMessage::class);
    });

    it('does not dispatch when telegram is not configured', function () {
        config(['services.telegram.token' => null, 'services.telegram.channel' => null]);
        Bus::fake();

        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);
        IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $this->user->id,
            'kind' => 'created',
            'payload' => null,
            'occurred_at' => now(),
        ]);

        Bus::assertNotDispatched(SendTelegramMessage::class);
    });
});

describe('comments', function () {
    it('formats a comment with an excerpt and a link', function () {
        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);
        $comment = Comment::create([
            'issue_id' => $issue->id,
            'user_id' => $this->user->id,
            'parent_comment_id' => null,
            'body' => 'Esto está casi listo, solo falta el QA.',
        ]);

        $text = CommentTelegramFormatter::format($comment);

        expect($text)->toContain('ENG-'.$issue->number)
            ->and($text)->toContain('Comentario:')
            ->and($text)->toContain('solo falta el QA')
            ->and($text)->toContain('/issues/ENG-'.$issue->number);
    });

    it('dispatches a telegram job when a comment is created', function () {
        Bus::fake();

        $issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);
        Comment::create([
            'issue_id' => $issue->id,
            'user_id' => $this->user->id,
            'parent_comment_id' => null,
            'body' => 'Un comentario.',
        ]);

        Bus::assertDispatched(SendTelegramMessage::class);
    });
});

describe('milestones', function () {
    it('logs a milestone_added activity and posts it to the feed', function () {
        Bus::fake();

        $project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
        $milestone = $project->milestones()->create(['name' => 'Beta', 'sort_order' => 1]);

        app(ProjectActivityRecorder::class)->milestoneAdded($project, $milestone, $this->user->id);

        expect(ProjectActivity::where('project_id', $project->id)->where('kind', 'milestone_added')->exists())
            ->toBeTrue();
        Bus::assertDispatched(SendTelegramMessage::class);
    });
});

it('posts to an explicit chat id when given', function () {
    config(['services.telegram.token' => 'TESTTOKEN', 'services.telegram.channel' => '-100GLOBAL']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    (new SendTelegramMessage('<b>hi</b>', '-100OVERRIDE'))->handle();

    Http::assertSent(fn ($request) => $request['chat_id'] === '-100OVERRIDE');
});
