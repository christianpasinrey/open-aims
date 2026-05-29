<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Issues\Support\IssueMentionResolver;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->user = $fix['user'];
    $this->team = $fix['team'];
    $this->workspace = $fix['workspace'];
    $this->states = $fix['states'];
    $this->issue = makeIssue($this->team, $this->workspace, $this->states['Todo']);
});

it('mentions the assignee on an assigned activity when they have a handle', function () {
    $assignee = User::factory()->create(['telegram_username' => 'ana']);
    $activity = IssueActivity::create([
        'issue_id' => $this->issue->id,
        'actor_user_id' => $this->user->id,
        'kind' => 'assigned',
        'payload' => ['user_id' => $assignee->id, 'user_name' => $assignee->name],
        'occurred_at' => now(),
    ]);

    expect(IssueMentionResolver::forActivity($activity))->toBe('@ana');
});

it('does not mention on self-assignment', function () {
    $self = User::factory()->create(['telegram_username' => 'ana']);
    $activity = IssueActivity::create([
        'issue_id' => $this->issue->id,
        'actor_user_id' => $self->id,
        'kind' => 'assigned',
        'payload' => ['user_id' => $self->id, 'user_name' => $self->name],
        'occurred_at' => now(),
    ]);

    expect(IssueMentionResolver::forActivity($activity))->toBeNull();
});

it('does not mention an assignee without a handle', function () {
    $assignee = User::factory()->create(['telegram_username' => null]);
    $activity = IssueActivity::create([
        'issue_id' => $this->issue->id,
        'actor_user_id' => $this->user->id,
        'kind' => 'assigned',
        'payload' => ['user_id' => $assignee->id, 'user_name' => $assignee->name],
        'occurred_at' => now(),
    ]);

    expect(IssueMentionResolver::forActivity($activity))->toBeNull();
});

it('returns null for non-assignment activities', function () {
    $activity = IssueActivity::create([
        'issue_id' => $this->issue->id,
        'actor_user_id' => $this->user->id,
        'kind' => 'status_changed',
        'payload' => ['from' => ['name' => 'Todo'], 'to' => ['name' => 'Done']],
        'occurred_at' => now(),
    ]);

    expect(IssueMentionResolver::forActivity($activity))->toBeNull();
});

it('mentions the parent comment author on a reply', function () {
    $parentAuthor = User::factory()->create(['telegram_username' => '@luis']);
    $parent = Comment::create([
        'issue_id' => $this->issue->id,
        'user_id' => $parentAuthor->id,
        'parent_comment_id' => null,
        'body' => 'original',
    ]);
    $reply = Comment::create([
        'issue_id' => $this->issue->id,
        'user_id' => $this->user->id,
        'parent_comment_id' => $parent->id,
        'body' => 'reply',
    ]);

    expect(IssueMentionResolver::forComment($reply))->toBe('@luis');
});

it('returns null for a top-level comment', function () {
    $comment = Comment::create([
        'issue_id' => $this->issue->id,
        'user_id' => $this->user->id,
        'parent_comment_id' => null,
        'body' => 'top level',
    ]);

    expect(IssueMentionResolver::forComment($comment))->toBeNull();
});

it('does not mention on a self-reply', function () {
    $author = User::factory()->create(['telegram_username' => 'luis']);
    $parent = Comment::create([
        'issue_id' => $this->issue->id,
        'user_id' => $author->id,
        'parent_comment_id' => null,
        'body' => 'original',
    ]);
    $reply = Comment::create([
        'issue_id' => $this->issue->id,
        'user_id' => $author->id,
        'parent_comment_id' => $parent->id,
        'body' => 'self reply',
    ]);

    expect(IssueMentionResolver::forComment($reply))->toBeNull();
});
