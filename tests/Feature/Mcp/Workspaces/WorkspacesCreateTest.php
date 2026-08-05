<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Models\User;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Mcp\Tools\WorkspacesCreate;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\Support\WorkspaceProvisioner;

it('creates a workspace with the caller as owner and a usable team', function () {
    $user = User::factory()->create();

    AimsServer::actingAs($user)->tool(WorkspacesCreate::class, ['name' => 'Acme Labs'])
        ->assertOk()
        ->assertSee('"name":"Acme Labs"')
        ->assertSee('"join_policy":"request"')
        ->assertSee('"role":"owner"');

    $ws = Workspace::where('name', 'Acme Labs')->first();
    expect($ws)->not->toBeNull()
        ->and($ws->owner_user_id)->toBe($user->id)
        ->and($ws->slug)->toStartWith('acme-labs-');

    expect(WorkspaceMember::where('workspace_id', $ws->id)
        ->where('user_id', $user->id)
        ->where('role', 'owner')
        ->exists())->toBeTrue();

    $team = Team::withoutGlobalScopes()->where('workspace_id', $ws->id)->first();
    expect($team)->not->toBeNull()->and($team->name)->toBe('Acme Labs');
    expect(WorkflowState::where('team_id', $team->id)->count())->toBe(6);
});

it('returns the generated slug so later calls can scope to the new workspace', function () {
    $user = User::factory()->create();

    $response = AimsServer::actingAs($user)->tool(WorkspacesCreate::class, ['name' => 'Scoped Corp'])->assertOk();

    $slug = (string) Workspace::where('name', 'Scoped Corp')->value('slug');
    expect($slug)->not->toBe('');

    $response
        ->assertSee('"slug":"'.$slug.'"')
        ->assertSee("Pass workspace_slug='{$slug}'");
});

it('names the initial team from team_name and team_key when given', function () {
    $user = User::factory()->create();

    AimsServer::actingAs($user)->tool(WorkspacesCreate::class, [
        'name' => 'Acme Labs',
        'team_name' => 'Engineering',
        'team_key' => 'ENG',
    ])->assertOk()->assertSee('"key":"ENG"');

    $ws = Workspace::where('name', 'Acme Labs')->first();
    $team = Team::withoutGlobalScopes()->where('workspace_id', $ws->id)->first();
    expect($team->name)->toBe('Engineering')->and($team->key)->toBe('ENG');
});

it('accepts an explicit join_policy', function () {
    $user = User::factory()->create();

    AimsServer::actingAs($user)->tool(WorkspacesCreate::class, ['name' => 'Open Corp', 'join_policy' => 'open'])
        ->assertOk()
        ->assertSee('"join_policy":"open"');
});

it('rejects an unknown join_policy without creating anything', function () {
    $user = User::factory()->create();

    AimsServer::actingAs($user)->tool(WorkspacesCreate::class, ['name' => 'Bad Corp', 'join_policy' => 'superadmin'])
        ->assertHasErrors(["Unknown join_policy 'superadmin'", 'open, request, private']);

    expect(Workspace::where('name', 'Bad Corp')->exists())->toBeFalse();
});

it('requires a name', function () {
    $user = User::factory()->create();

    AimsServer::actingAs($user)->tool(WorkspacesCreate::class, ['name' => '   '])
        ->assertHasErrors(['name is required.']);

    expect(Workspace::where('owner_user_id', $user->id)->exists())->toBeFalse();
});

it('refuses to create past the per-user workspace limit', function () {
    $user = User::factory()->create();

    $limit = app(WorkspaceProvisioner::class)->limitPerUser();
    for ($i = 0; $i < $limit; $i++) {
        Workspace::factory()->create(['owner_user_id' => $user->id]);
    }

    AimsServer::actingAs($user)->tool(WorkspacesCreate::class, ['name' => 'One Too Many'])
        ->assertHasErrors(["You already own the maximum of {$limit} workspaces."]);

    expect(Workspace::where('name', 'One Too Many')->exists())->toBeFalse();
});
