<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Projects\Mcp\Tools\ProjectsCreate;
use App\Modules\Projects\Models\Project;

it('rejects projects-create when goal and scope are missing', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(ProjectsCreate::class, [
        'name' => 'No scrum',
        'team_keys' => ['ENG'],
        'plan_content' => 'plan body',
        'plan_format' => 'md',
    ])->assertHasErrors(['goal', 'scope']);

    expect(Project::where('name', 'No scrum')->exists())->toBeFalse();
});

it('rejects projects-create when only the goal is supplied', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(ProjectsCreate::class, [
        'name' => 'Half scrum',
        'team_keys' => ['ENG'],
        'goal' => 'Ship onboarding',
        'plan_content' => 'plan body',
        'plan_format' => 'md',
    ])->assertHasErrors(['scope']);

    expect(Project::where('name', 'Half scrum')->exists())->toBeFalse();
});

it('treats a blank goal as missing', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(ProjectsCreate::class, [
        'name' => 'Blank goal',
        'team_keys' => ['ENG'],
        'goal' => '   ',
        'scope' => 'In: the API. Out: the mobile client.',
        'plan_content' => 'plan body',
        'plan_format' => 'md',
    ])->assertHasErrors(['goal']);

    expect(Project::where('name', 'Blank goal')->exists())->toBeFalse();
});

it('accepts projects-create without goal or scope when skip_scrum is true', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(ProjectsCreate::class, [
        'name' => 'Skipped scrum',
        'team_keys' => ['ENG'],
        'skip_scrum' => true,
        'plan_content' => 'plan body',
        'plan_format' => 'md',
    ])->assertOk();

    $project = Project::where('name', 'Skipped scrum')->firstOrFail();
    expect($project->description)->toBeNull();
});

it('renders goal and scope into the project description under their headings', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(ProjectsCreate::class, [
        'name' => 'Full scrum',
        'team_keys' => ['ENG'],
        'description' => 'Existing context paragraph.',
        'goal' => 'Cut checkout drop-off from 40% to 15%.',
        'scope' => 'In: checkout funnel. Out: payment provider migration.',
        'plan_content' => 'plan body',
        'plan_format' => 'md',
    ])->assertOk();

    $project = Project::where('name', 'Full scrum')->firstOrFail();

    expect($project->description)->toBe(
        "Existing context paragraph.\n\n"
        ."## Goal\n\nCut checkout drop-off from 40% to 15%.\n\n"
        ."## Scope\n\nIn: checkout funnel. Out: payment provider migration."
    );
});

it('renders goal and scope even when no description is supplied', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(ProjectsCreate::class, [
        'name' => 'Scrum only',
        'team_keys' => ['ENG'],
        'goal' => 'A measurable outcome.',
        'scope' => 'In: one thing. Out: everything else.',
        'plan_content' => 'plan body',
        'plan_format' => 'md',
    ])->assertOk();

    $project = Project::where('name', 'Scrum only')->firstOrFail();

    expect($project->description)->toBe(
        "## Goal\n\nA measurable outcome.\n\n"
        ."## Scope\n\nIn: one thing. Out: everything else."
    );
});
