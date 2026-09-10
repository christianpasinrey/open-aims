<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Core\Contracts\ProvidesMcpTools;
use App\Core\Registries\ModuleRegistry;
use App\Mcp\Prompts\BreakdownEpic;
use App\Mcp\Prompts\PlanSprint;
use App\Mcp\Prompts\WriteIssue;
use App\Mcp\Resources\DiagramsGuide;
use App\Mcp\Resources\DocumentationGuide;
use App\Mcp\Resources\GraphsGuide;
use App\Mcp\Resources\PlanningGuide;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Aims')]
#[Version('1.0.0')]
#[Instructions(
    "Operate an AIMS issue tracker from natural language.\n\n".
    'ORIENT BEFORE ANY WRITE: call `current` first. When it lists more than one workspace, pass '.
    '`workspace_slug` on every call — omitting it uses the first membership by id, often the wrong '.
    "board.\n\n".
    'Tools are kebab-case, never dotted: current, workspaces-list, workspaces-create (makes a NEW '.
    'board), issues-list, issues-create, issues-update, issues-get, issues-link, projects-list, '.
    'projects-create, cycles-create, inbox-list, labels-ensure, search, graphs-schema, graphs-attach, '.
    "graphs-map, graphs-impact.\n\n".
    'Identifiers are TEAMKEY-N (LAM-275). Project slugs come from `projects-list`; cycles are '.
    "(team_key, number); 'me' is the caller.\n\n".
    'DOCUMENT EVERY PROJECT, MILESTONE AND ISSUE THE SAME WAY: HTML plan with Mermaid and Chart.js, '.
    'labels, acceptance criteria and a code graph (stage planned before coding, implemented when '.
    'done). Follow aims://guides/documentation; aims://guides/planning covers Scrum, '.
    'aims://guides/diagrams plan markup, aims://guides/graphs and `graphs-schema` the graph model.'
)]
class AimsServer extends Server
{
    /**
     * Tools are auto-discovered from each module's manifest. A module
     * exposes its tools by implementing `ProvidesMcpTools`. Adding a
     * new domain module → register it in `bootstrap/providers.php` (or
     * via the existing module auto-loader) and its tools appear here
     * automatically. Nothing to maintain in this list.
     *
     * @var array<class-string<Tool>>
     */
    protected array $tools = [];

    /**
     * Written guides the client can read instead of guessing: the
     * documentation protocol every work item follows, the planning
     * methodology, the plan rendering contract (mermaid / Chart.js markup)
     * and the code graph model.
     *
     * All are `Laravel\Mcp\Server\Resource` subclasses.
     *
     * @var array<int, class-string>
     */
    protected array $resources = [
        DocumentationGuide::class,
        PlanningGuide::class,
        DiagramsGuide::class,
        GraphsGuide::class,
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        PlanSprint::class,
        BreakdownEpic::class,
        WriteIssue::class,
    ];

    // Override the package's pagination defaults — we ship ~30 tools and
    // some clients (Claude Desktop) don't follow nextCursor on tools/list,
    // which would hide every tool past index 14.
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 200;

    /**
     * {@inheritDoc}
     */
    protected function boot(): void
    {
        parent::boot();

        $registry = app(ModuleRegistry::class);
        foreach ($registry->all() as $manifest) {
            if ($manifest instanceof ProvidesMcpTools) {
                foreach ($manifest->mcpTools() as $toolClass) {
                    $this->tools[] = $toolClass;
                }
            }
        }
    }
}
