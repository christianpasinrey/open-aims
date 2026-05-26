<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\User;
use App\Modules\Issues\Models\Issue;

it('serves the raw plan body to a workspace member', function () {
    $fix = makeWorkspaceFixture();
    $issue = Issue::factory()->create([
        'workspace_id' => $fix['workspace']->id, 'team_id' => $fix['team']->id,
        'workflow_state_id' => $fix['states']['Triage']->id, 'number' => 1,
    ]);
    $plan = Plan::create([
        'planable_type' => $issue->getMorphClass(), 'planable_id' => $issue->id,
        'format' => 'html', 'content' => '<h1>hi</h1>', 'version' => 1, 'is_current' => true,
    ]);

    $this->actingAs($fix['user'])
        ->get("/plans/{$plan->id}/raw")
        ->assertOk()
        ->assertSee('hi');
});

it('denies a non-member', function () {
    $fix = makeWorkspaceFixture();
    $issue = Issue::factory()->create([
        'workspace_id' => $fix['workspace']->id, 'team_id' => $fix['team']->id,
        'workflow_state_id' => $fix['states']['Triage']->id, 'number' => 1,
    ]);
    $plan = Plan::create([
        'planable_type' => $issue->getMorphClass(), 'planable_id' => $issue->id,
        'format' => 'html', 'content' => '<h1>secret</h1>', 'version' => 1, 'is_current' => true,
    ]);

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get("/plans/{$plan->id}/raw")->assertForbidden();
});
