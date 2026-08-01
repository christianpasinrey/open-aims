<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Models\User;
use App\Modules\Issues\Mcp\Tools\IssuesCreate;
use App\Modules\Issues\Mcp\Tools\IssuesUpdate;
use App\Modules\Issues\Mcp\Tools\LabelsEnsure;
use App\Modules\Issues\Mcp\Tools\LabelsList;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Label;

if (! function_exists('mcpToolJson')) {
    /**
     * @return array<string, mixed>
     */
    function mcpToolJson(object $response): array
    {
        $method = new ReflectionMethod($response, 'content');
        $method->setAccessible(true);

        /** @var array<int, string> $texts */
        $texts = $method->invoke($response);

        return json_decode(implode('', $texts), true) ?: [];
    }
}

if (! function_exists('gherkinSample')) {
    /** @return list<string> */
    function gherkinSample(): array
    {
        return ['Given a signed-in user, When they submit the form, Then the issue is created.'];
    }
}

it('reports applied and unknown label names on create', function () {
    $fix = makeWorkspaceFixture();
    Label::create(['team_id' => $fix['team']->id, 'name' => 'bug', 'color' => '#ef4444']);

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
            'team_key' => 'ENG',
            'title' => 'labelled',
            'labels' => ['bug', 'nope', 'also-missing'],
            'acceptance_criteria' => gherkinSample(),
            'skip_plan' => true,
        ])->assertOk()
    );

    expect($json['labels_applied'])->toBe(['bug'])
        ->and($json['labels_unknown'])->toBe(['nope', 'also-missing']);

    $issue = Issue::where('title', 'labelled')->firstOrFail();
    expect($issue->labels()->pluck('name')->all())->toBe(['bug']);
});

it('matches existing labels case-insensitively', function () {
    $fix = makeWorkspaceFixture();
    Label::create(['team_id' => $fix['team']->id, 'name' => 'Bug', 'color' => '#ef4444']);

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
            'team_key' => 'ENG',
            'title' => 'case',
            'labels' => ['BUG'],
            'acceptance_criteria' => gherkinSample(),
            'skip_plan' => true,
        ])->assertOk()
    );

    expect($json['labels_applied'])->toBe(['Bug'])
        ->and($json['labels_unknown'])->toBe([]);
});

it('reports unknown label names on update', function () {
    $fix = makeWorkspaceFixture();
    Label::create(['team_id' => $fix['team']->id, 'name' => 'infra', 'color' => '#22c55e']);
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
            'identifier' => 'ENG-'.$issue->number,
            'labels' => ['infra', 'typo-label'],
            'skip_plan' => true,
        ])->assertOk()
    );

    expect($json['labels_applied'])->toBe(['infra'])
        ->and($json['labels_unknown'])->toBe(['typo-label'])
        ->and($issue->fresh()->labels()->pluck('name')->all())->toBe(['infra']);
});

it('lists the labels of a team', function () {
    $fix = makeWorkspaceFixture();
    Label::create(['team_id' => $fix['team']->id, 'name' => 'zeta', 'color' => '#111111']);
    Label::create(['team_id' => $fix['team']->id, 'name' => 'alpha', 'color' => '#222222', 'description' => 'first']);

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(LabelsList::class, ['team_key' => 'ENG'])->assertOk()
    );

    expect($json['count'])->toBe(2)
        ->and(array_column($json['labels'], 'name'))->toBe(['alpha', 'zeta'])
        ->and($json['labels'][0]['description'])->toBe('first');
});

it('creates only the missing labels with labels-ensure', function () {
    $fix = makeWorkspaceFixture();
    Label::create(['team_id' => $fix['team']->id, 'name' => 'Bug', 'color' => '#ef4444']);

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(LabelsEnsure::class, [
            'team_key' => 'ENG',
            'labels' => ['bug', 'chore', 'spike'],
            'color' => '#22c55e',
        ])->assertOk()
    );

    expect($json['created'])->toBe(['chore', 'spike'])
        ->and($json['existing'])->toBe(['Bug'])
        ->and(Label::where('team_id', $fix['team']->id)->count())->toBe(3)
        ->and(Label::where('name', 'chore')->value('color'))->toBe('#22c55e');
});

it('is idempotent when labels-ensure runs twice', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(LabelsEnsure::class, [
        'team_key' => 'ENG', 'labels' => ['docs'],
    ])->assertOk();

    $json = mcpToolJson(
        AimsServer::actingAs($fix['user'])->tool(LabelsEnsure::class, [
            'team_key' => 'ENG', 'labels' => ['docs'],
        ])->assertOk()
    );

    expect($json['created'])->toBe([])
        ->and($json['existing'])->toBe(['docs'])
        ->and(Label::where('team_id', $fix['team']->id)->count())->toBe(1);
});

it('rejects an assignee who is not a member of the workspace on create', function () {
    $fix = makeWorkspaceFixture();
    $outsider = User::factory()->create(['email' => 'outsider@example.com']);

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'bad assignee',
        'assignee' => 'outsider@example.com',
        'acceptance_criteria' => gherkinSample(),
        'skip_plan' => true,
    ])->assertHasErrors(['is not a member of workspace']);

    expect($outsider->exists)->toBeTrue()
        ->and(Issue::where('title', 'bad assignee')->exists())->toBeFalse();
});

it('rejects an assignee who is not a member of the workspace on update', function () {
    $fix = makeWorkspaceFixture();
    $outsider = User::factory()->create(['email' => 'outsider2@example.com']);
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'assignee' => (string) $outsider->id,
        'skip_plan' => true,
    ])->assertHasErrors(['is not a member of workspace']);

    expect($issue->fresh()->assignee_user_id)->toBeNull();
});

it('accepts "me" as the assignee', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'mine',
        'assignee' => 'me',
        'acceptance_criteria' => gherkinSample(),
        'skip_plan' => true,
    ])->assertOk();

    expect(Issue::where('title', 'mine')->firstOrFail()->assignee_user_id)->toBe($fix['user']->id);
});
