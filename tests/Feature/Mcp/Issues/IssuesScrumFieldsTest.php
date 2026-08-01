<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Issues\Mcp\Tools\IssuesCreate;
use App\Modules\Issues\Mcp\Tools\IssuesUpdate;
use App\Modules\Issues\Models\Issue;

if (! function_exists('gherkinSample')) {
    /** @return list<string> */
    function gherkinSample(): array
    {
        return ['Given a signed-in user, When they submit the form, Then the issue is created.'];
    }
}

it('rejects a create without acceptance_criteria and shows an example', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'no criteria',
        'skip_plan' => true,
    ])->assertHasErrors(['acceptance_criteria is required', 'Given ']);

    expect(Issue::where('title', 'no criteria')->exists())->toBeFalse();
});

it('accepts a create without acceptance_criteria when skip_scrum is true', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'skipped',
        'skip_plan' => true,
        'skip_scrum' => true,
    ])->assertOk();

    $issue = Issue::where('title', 'skipped')->firstOrFail();
    expect($issue->description)->toBeNull();
});

it('renders acceptance criteria into the description under a heading', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'with criteria',
        'description' => 'Some context about the work.',
        'acceptance_criteria' => array_merge(gherkinSample(), [
            'Given an empty title, When they submit, Then a validation error is shown.',
        ]),
        'skip_plan' => true,
    ])->assertOk();

    $issue = Issue::where('title', 'with criteria')->firstOrFail();

    expect($issue->description)->toBe(
        "Some context about the work.\n\n"
        ."## Acceptance criteria\n\n"
        ."- Given a signed-in user, When they submit the form, Then the issue is created.\n"
        .'- Given an empty title, When they submit, Then a validation error is shown.'
    );
});

it('renders acceptance criteria even with no description supplied', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'criteria only',
        'acceptance_criteria' => gherkinSample(),
        'skip_plan' => true,
    ])->assertOk();

    $issue = Issue::where('title', 'criteria only')->firstOrFail();
    expect($issue->description)->toStartWith('## Acceptance criteria');
});

it('rejects an acceptance criterion that is not a Gherkin line', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'bad gherkin',
        'acceptance_criteria' => ['it should just work'],
        'skip_plan' => true,
    ])->assertHasErrors(['Given, When and Then']);

    expect(Issue::where('title', 'bad gherkin')->exists())->toBeFalse();
});

// This workspace writes its issues in Spanish, so an English-only keyword check
// would reject perfectly valid criteria.
it('accepts acceptance criteria written in Spanish Gherkin', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'gherkin en castellano',
        'acceptance_criteria' => [
            'Dado un revisor con sesión iniciada, Cuando abre una incidencia cerrada, Entonces ve el botón de reabrir.',
        ],
        'skip_plan' => true,
    ])->assertOk();

    $issue = Issue::where('title', 'gherkin en castellano')->firstOrFail();

    expect($issue->description)->toContain('## Acceptance criteria')
        ->and($issue->description)->toContain('Entonces ve el botón de reabrir');
});

it('creates an issue with an estimate and a parent in one call', function () {
    $fix = makeWorkspaceFixture();
    $epic = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Epic']);

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'story',
        'estimate' => 5,
        'parent' => 'ENG-'.$epic->number,
        'acceptance_criteria' => gherkinSample(),
        'skip_plan' => true,
    ])->assertOk();

    $story = Issue::where('title', 'story')->firstOrFail();

    expect($story->estimate)->toBe(5.0)
        ->and($story->parent_issue_id)->toBe($epic->id);
});

it('rejects a parent that does not exist in the workspace', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'orphan',
        'parent' => 'ENG-999',
        'acceptance_criteria' => gherkinSample(),
        'skip_plan' => true,
    ])->assertHasErrors(['Parent issue']);

    expect(Issue::where('title', 'orphan')->exists())->toBeFalse();
});

it('attaches and detaches a parent through issues-update', function () {
    $fix = makeWorkspaceFixture();
    $epic = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Epic']);
    $story = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], ['title' => 'Story']);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$story->number,
        'parent' => 'ENG-'.$epic->number,
        'skip_plan' => true,
    ])->assertOk();

    expect($story->fresh()->parent_issue_id)->toBe($epic->id);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$story->number,
        'parent' => null,
        'skip_plan' => true,
    ])->assertOk();

    expect($story->fresh()->parent_issue_id)->toBeNull();
});

it('refuses to make an issue its own parent', function () {
    $fix = makeWorkspaceFixture();
    $issue = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo']);

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number,
        'parent' => 'ENG-'.$issue->number,
        'skip_plan' => true,
    ])->assertHasErrors(['its own parent']);
});
