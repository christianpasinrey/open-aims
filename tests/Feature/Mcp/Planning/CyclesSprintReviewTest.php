<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Cycles\Mcp\Tools\CyclesCreate;
use App\Modules\Cycles\Mcp\Tools\CyclesGet;
use App\Modules\Cycles\Models\Cycle;

it('returns the issues in the cycle from cycles-get', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(CyclesCreate::class, [
        'team_key' => 'ENG',
        'starts_at' => '2026-08-03',
        'ends_at' => '2026-08-14',
        'name' => 'Sprint 1',
    ])->assertOk();

    $cycle = Cycle::where('team_id', $fix['team']->id)->firstOrFail();

    $inProgress = makeIssue($fix['team'], $fix['workspace'], $fix['states']['In Progress'], [
        'cycle_id' => $cycle->id,
        'title' => 'Wire the importer',
        'estimate' => 3.0,
        'assignee_user_id' => $fix['user']->id,
    ]);
    $done = makeIssue($fix['team'], $fix['workspace'], $fix['states']['Done'], [
        'cycle_id' => $cycle->id,
        'title' => 'Backfill the index',
        'estimate' => 5.0,
    ]);

    AimsServer::actingAs($fix['user'])->tool(CyclesGet::class, [
        'team_key' => 'ENG',
        'number' => $cycle->number,
    ])->assertOk()->assertSee([
        'ENG-'.$inProgress->number,
        'Wire the importer',
        'In Progress',
        $fix['user']->name,
        'ENG-'.$done->number,
        'Backfill the index',
        'Done',
    ]);
});

it('keeps the existing progress keys alongside the issues list', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(CyclesCreate::class, [
        'team_key' => 'ENG',
        'starts_at' => '2026-08-03',
        'ends_at' => '2026-08-14',
    ])->assertOk();

    $cycle = Cycle::where('team_id', $fix['team']->id)->firstOrFail();

    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Done'], [
        'cycle_id' => $cycle->id, 'title' => 'shipped',
    ]);

    AimsServer::actingAs($fix['user'])->tool(CyclesGet::class, [
        'team_key' => 'ENG',
        'number' => $cycle->number,
    ])->assertOk()->assertSee(['"progress"', '"assignees"', '"issues"', '"percent":100']);
});

it('excludes archived issues from the cycle issue list', function () {
    $fix = makeWorkspaceFixture();

    AimsServer::actingAs($fix['user'])->tool(CyclesCreate::class, [
        'team_key' => 'ENG',
        'starts_at' => '2026-08-03',
        'ends_at' => '2026-08-14',
    ])->assertOk();

    $cycle = Cycle::where('team_id', $fix['team']->id)->firstOrFail();

    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'cycle_id' => $cycle->id, 'title' => 'live issue',
    ]);
    makeIssue($fix['team'], $fix['workspace'], $fix['states']['Todo'], [
        'cycle_id' => $cycle->id, 'title' => 'archived issue', 'archived_at' => now(),
    ]);

    AimsServer::actingAs($fix['user'])->tool(CyclesGet::class, [
        'team_key' => 'ENG',
        'number' => $cycle->number,
    ])->assertOk()
        ->assertSee('live issue')
        ->assertDontSee('archived issue');
});
