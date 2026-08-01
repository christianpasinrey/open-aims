<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Cycles\Mcp\Tools\CyclesList;
use App\Modules\Initiatives\Mcp\Tools\InitiativesList;
use App\Modules\Projects\Mcp\Tools\ProjectsCreate;
use App\Modules\Projects\Mcp\Tools\ProjectsList;
use App\Modules\Projects\Models\Project;

it('errors instead of falling back when workspace_slug is not one of the user memberships', function () {
    $mine = makeWorkspaceFixture();
    $theirs = makeWorkspaceFixture(['workspace' => ['slug' => 'not-mine']]);

    // Re-bind my workspace: the second fixture bound theirs into the container.
    app()->instance('current.workspace', $mine['workspace']);

    AimsServer::actingAs($mine['user'])->tool(ProjectsCreate::class, [
        'workspace_slug' => $theirs['workspace']->slug,
        'name' => 'Cross workspace write',
        'team_keys' => [$theirs['team']->key],
        'goal' => 'Should never be created.',
        'scope' => 'In: nothing. Out: everything.',
        'plan_content' => 'plan body',
        'plan_format' => 'md',
    ])->assertHasErrors([$theirs['workspace']->slug, 'not a member']);

    // The critical part: nothing was written to either workspace.
    expect(Project::withoutGlobalScopes()->where('name', 'Cross workspace write')->exists())
        ->toBeFalse();
});

it('errors on an unknown workspace_slug rather than using the default membership', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(ProjectsList::class, [
        'workspace_slug' => 'does-not-exist',
    ])->assertHasErrors(['does-not-exist']);
});

it('surfaces the same workspace error from every planning read tool', function () {
    $mine = makeWorkspaceFixture();
    $theirs = makeWorkspaceFixture(['workspace' => ['slug' => 'other-org']]);
    app()->instance('current.workspace', $mine['workspace']);

    foreach ([ProjectsList::class, InitiativesList::class] as $tool) {
        AimsServer::actingAs($mine['user'])->tool($tool, [
            'workspace_slug' => $theirs['workspace']->slug,
        ])->assertHasErrors(['not a member']);
    }

    AimsServer::actingAs($mine['user'])->tool(CyclesList::class, [
        'team_key' => 'ENG',
        'workspace_slug' => $theirs['workspace']->slug,
    ])->assertHasErrors(['not a member']);
});

it('lists the slugs the user may actually use in the error', function () {
    $mine = makeWorkspaceFixture(['workspace' => ['slug' => 'my-org']]);
    $theirs = makeWorkspaceFixture(['workspace' => ['slug' => 'their-org']]);
    app()->instance('current.workspace', $mine['workspace']);

    AimsServer::actingAs($mine['user'])->tool(ProjectsList::class, [
        'workspace_slug' => 'their-org',
    ])->assertHasErrors(['Available: my-org']);
});
