<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceJoinRequest;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
});

function ws(string $name, string $policy): Workspace
{
    return Workspace::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'owner_user_id' => User::factory()->create()->id,
        'join_policy' => $policy,
    ]);
}

it('returns open and request workspaces matching the query with relationship', function () {
    $open = ws('Alpha Open', 'open');
    $req = ws('Alpha Request', 'request');
    ws('Alpha Private', 'private');

    $res = $this->actingAs($this->user)->getJson('/workspaces/search?q=Alpha');
    $res->assertOk();
    $slugs = collect($res->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain($open->slug)->and($slugs)->toContain($req->slug);
    $names = collect($res->json('data'))->pluck('name')->all();
    expect($names)->not->toContain('Alpha Private');
    $relations = collect($res->json('data'))->keyBy('slug');
    expect($relations[$open->slug]['relationship'])->toBe('open')
        ->and($relations[$req->slug]['relationship'])->toBe('request');
});

it('marks member and pending relationships', function () {
    $memberWs = ws('Beta Member', 'open');
    WorkspaceMember::create(['workspace_id' => $memberWs->id, 'user_id' => $this->user->id, 'role' => 'member', 'joined_at' => now()]);
    $pendingWs = ws('Beta Pending', 'request');
    WorkspaceJoinRequest::create(['workspace_id' => $pendingWs->id, 'user_id' => $this->user->id, 'status' => 'pending']);

    $res = $this->actingAs($this->user)->getJson('/workspaces/search?q=Beta');
    $rel = collect($res->json('data'))->keyBy('slug');
    expect($rel[$memberWs->slug]['relationship'])->toBe('member')
        ->and($rel[$pendingWs->slug]['relationship'])->toBe('pending');
});

it('returns empty for a blank query', function () {
    ws('Gamma', 'open');
    $res = $this->actingAs($this->user)->getJson('/workspaces/search?q=');
    expect($res->json('data'))->toBe([]);
});
