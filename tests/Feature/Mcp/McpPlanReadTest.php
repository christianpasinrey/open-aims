<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use App\Modules\Issues\Mcp\Tools\IssuesCreate;
use App\Modules\Issues\Mcp\Tools\IssuesGet;
use App\Modules\Issues\Models\Issue;

it('returns the current plan content and libs from issues.get', function () {
    $fix = makeWorkspaceFixture();
    AimsServer::actingAs($fix['user'])->tool(IssuesCreate::class, [
        'team_key' => 'ENG', 'title' => 'readable',
        'plan_content' => '<pre class="mermaid">graph TD; A-->B</pre>',
        'plan_format' => 'html', 'plan_libs' => ['mermaid'],
    ])->assertOk();
    $issue = Issue::where('title', 'readable')->firstOrFail();

    AimsServer::actingAs($fix['user'])->tool(IssuesGet::class, [
        'identifier' => 'ENG-'.$issue->number,
    ])->assertOk()->assertSee('graph TD');
});
