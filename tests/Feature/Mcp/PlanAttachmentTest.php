<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Models\Plan;
use App\Modules\Issues\Mcp\Tools\IssuesCreate;
use App\Modules\Issues\Mcp\Tools\IssuesUpdate;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Mcp\Tools\ProjectsCreate;
use App\Modules\Projects\Models\Project;

it('creates an issue with an HTML plan and stores libs', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG',
        'title' => 'rich plan',
        'plan_content' => '<pre class="mermaid">graph TD; A-->B</pre>',
        'plan_format' => 'html',
        'plan_libs' => ['mermaid'],
    ])->assertOk();

    $issue = Issue::where('title', 'rich plan')->firstOrFail();
    $plan = Plan::where('planable_type', $issue->getMorphClass())
        ->where('planable_id', $issue->id)->where('is_current', true)->firstOrFail();

    expect($plan->format)->toBe('html')
        ->and($plan->libs)->toBe(['mermaid'])
        ->and($plan->content)->toContain('graph TD');
});

it('demotes the previous plan when a new one is attached', function () {
    $fix = makeWorkspaceFixture();
    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG', 'title' => 'demo', 'plan_content' => 'v1', 'plan_format' => 'md',
    ])->assertOk();
    $issue = Issue::where('title', 'demo')->firstOrFail();

    AimsServer::actingAs($fix['user'])->tool(IssuesUpdate::class, [
        'identifier' => 'ENG-'.$issue->number, 'plan_content' => 'v2', 'plan_format' => 'md',
    ])->assertOk();

    $current = Plan::where('planable_id', $issue->id)->where('is_current', true)->get();
    expect($current)->toHaveCount(1)
        ->and($current->first()->content)->toBe('v2')
        ->and($current->first()->version)->toBe(2)
        ->and(Plan::where('planable_id', $issue->id)->count())->toBe(2);

    $archived = Plan::where('planable_id', $issue->id)->where('is_current', false)->get();
    expect($archived)->toHaveCount(1)
        ->and($archived->first()->version)->toBe(1);
});

it('rejects an invalid plan_libs value', function () {
    $fix = makeWorkspaceFixture();
    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG', 'title' => 'bad libs',
        'plan_content' => '<p>x</p>', 'plan_format' => 'html', 'plan_libs' => ['d3'],
    ])->assertHasErrors();
});

it('rejects plan_libs when plan_format is md', function () {
    $fix = makeWorkspaceFixture();
    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG', 'title' => 'md with libs',
        'plan_content' => 'plain', 'plan_format' => 'md', 'plan_libs' => ['mermaid'],
    ])->assertHasErrors();
});

it('creates a project with an HTML plan and stores libs', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(
        ProjectsCreate::class, [
            'name' => 'Rich project',
            'team_keys' => ['ENG'],
            'plan_content' => '<canvas id="c"></canvas>',
            'plan_format' => 'html',
            'plan_libs' => ['chart'],
        ]
    )->assertOk();

    $project = Project::where('name', 'Rich project')->firstOrFail();
    $plan = Plan::where('planable_type', $project->getMorphClass())
        ->where('planable_id', $project->id)->where('is_current', true)->firstOrFail();

    expect($plan->format)->toBe('html')->and($plan->libs)->toBe(['chart']);
});
