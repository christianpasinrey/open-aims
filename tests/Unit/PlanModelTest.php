<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Modules\Issues\Models\Issue;

it('returns only the current plan via the issue relation', function () {
    $fix = makeWorkspaceFixture();
    $issue = Issue::factory()->create([
        'workspace_id' => $fix['workspace']->id,
        'team_id' => $fix['team']->id,
        'workflow_state_id' => $fix['states']['Triage']->id,
        'number' => 1,
    ]);

    Plan::create([
        'planable_type' => $issue->getMorphClass(), 'planable_id' => $issue->id,
        'format' => 'html', 'content' => '<p>v1</p>', 'version' => 1, 'is_current' => false,
    ]);
    Plan::create([
        'planable_type' => $issue->getMorphClass(), 'planable_id' => $issue->id,
        'format' => 'html', 'content' => '<p>v2</p>', 'version' => 2, 'is_current' => true,
    ]);

    expect($issue->fresh()->plan->content)->toBe('<p>v2</p>')
        ->and($issue->fresh()->plan->libs)->toBeNull();
});
