<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Models\User;
use App\Modules\Teams\Mcp\Tools\TeamsList;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Mcp\Tools\Current;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;

/**
 * Add a second workspace (plus a team) that $user is a member of.
 *
 * @return array{workspace:Workspace,team:Team}
 */
function joinExtraWorkspace(User $user, string $name, string $slug, string $teamKey, string $role = 'member'): array
{
    $workspace = Workspace::factory()->create([
        'name' => $name,
        'slug' => $slug,
        'owner_user_id' => $user->id,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
        'joined_at' => now(),
    ]);

    $team = Team::factory()->create([
        'workspace_id' => $workspace->id,
        'key' => $teamKey,
        'name' => $teamKey.' Team',
    ]);

    return ['workspace' => $workspace, 'team' => $team];
}

it('lists every workspace the user belongs to with the active one flagged', function () {
    $fix = makeWorkspaceFixture([
        'workspace' => ['name' => 'Alpha Corp', 'slug' => 'alpha-corp'],
    ]);

    joinExtraWorkspace($fix['user'], 'Beta Corp', 'beta-corp', 'OPS');
    joinExtraWorkspace($fix['user'], 'Gamma Corp', 'gamma-corp', 'SUP');

    // No slug: resolves to the FIRST membership by id, i.e. the fixture one.
    AimsServer::actingAs($fix['user'])->tool(Current::class, [])
        ->assertOk()
        ->assertSee('"slug":"alpha-corp"')
        ->assertSee('"available_workspaces":[')
        ->assertSee('{"slug":"alpha-corp","name":"Alpha Corp","is_active":true}')
        ->assertSee('{"slug":"beta-corp","name":"Beta Corp","is_active":false}')
        ->assertSee('{"slug":"gamma-corp","name":"Gamma Corp","is_active":false}');
});

it('reports a single membership as the only available workspace', function () {
    $fix = makeWorkspaceFixture([
        'workspace' => ['name' => 'Solo Corp', 'slug' => 'solo-corp'],
    ]);

    AimsServer::actingAs($fix['user'])->tool(Current::class, [])
        ->assertOk()
        ->assertSee('"available_workspaces":[{"slug":"solo-corp","name":"Solo Corp","is_active":true}]');
});

it('does not expose workspaces the user is not a member of', function () {
    $fix = makeWorkspaceFixture([
        'workspace' => ['name' => 'Mine Corp', 'slug' => 'mine-corp'],
    ]);

    // A workspace owned by somebody else entirely.
    $stranger = User::factory()->create();
    joinExtraWorkspace($stranger, 'Foreign Corp', 'foreign-corp', 'FOR');

    AimsServer::actingAs($fix['user'])->tool(Current::class, [])
        ->assertOk()
        ->assertSee('"available_workspaces":[{"slug":"mine-corp","name":"Mine Corp","is_active":true}]')
        ->assertDontSee('foreign-corp');
});

it('selects the workspace named by an explicit slug', function () {
    $fix = makeWorkspaceFixture([
        'workspace' => ['name' => 'Alpha Corp', 'slug' => 'alpha-corp'],
    ]);

    joinExtraWorkspace($fix['user'], 'Beta Corp', 'beta-corp', 'OPS');

    AimsServer::actingAs($fix['user'])->tool(Current::class, ['workspace_slug' => 'beta-corp'])
        ->assertOk()
        ->assertSee('"slug":"beta-corp"')
        ->assertSee('"key":"OPS"')
        ->assertSee('{"slug":"beta-corp","name":"Beta Corp","is_active":true}')
        ->assertSee('{"slug":"alpha-corp","name":"Alpha Corp","is_active":false}')
        // The fixture team belongs to alpha-corp and must not leak in.
        ->assertDontSee('"key":"ENG"');
});

it('errors instead of falling back when the slug is a workspace the user does not belong to', function () {
    $fix = makeWorkspaceFixture([
        'workspace' => ['name' => 'Alpha Corp', 'slug' => 'alpha-corp'],
    ]);

    $stranger = User::factory()->create();
    joinExtraWorkspace($stranger, 'Foreign Corp', 'foreign-corp', 'FOR');

    AimsServer::actingAs($fix['user'])->tool(Current::class, ['workspace_slug' => 'foreign-corp'])
        ->assertHasErrors(["Workspace 'foreign-corp' not found or you are not a member.", 'alpha-corp'])
        // No silent fallback: none of the active workspace payload is returned.
        ->assertDontSee('"available_workspaces"')
        ->assertDontSee('"member_count"')
        ->assertDontSee('"key":"ENG"');
});

it('errors instead of falling back when the slug does not exist at all', function () {
    $fix = makeWorkspaceFixture([
        'workspace' => ['name' => 'Alpha Corp', 'slug' => 'alpha-corp'],
    ]);

    joinExtraWorkspace($fix['user'], 'Beta Corp', 'beta-corp', 'OPS');

    AimsServer::actingAs($fix['user'])->tool(Current::class, ['workspace_slug' => 'nope-does-not-exist'])
        ->assertHasErrors(["Workspace 'nope-does-not-exist' not found or you are not a member.", 'Available: alpha-corp, beta-corp.'])
        ->assertDontSee('"available_workspaces"');
});

it('surfaces the same slug-listing error from other workspace-scoped tools', function () {
    $fix = makeWorkspaceFixture([
        'workspace' => ['name' => 'Alpha Corp', 'slug' => 'alpha-corp'],
    ]);

    AimsServer::actingAs($fix['user'])->tool(TeamsList::class, ['workspace_slug' => 'foreign-corp'])
        ->assertHasErrors(["Workspace 'foreign-corp' not found or you are not a member.", 'Available: alpha-corp.'])
        ->assertDontSee('"key":"ENG"');
});
