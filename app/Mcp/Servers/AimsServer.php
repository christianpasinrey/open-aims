<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Core\Contracts\ProvidesMcpTools;
use App\Core\Registries\ModuleRegistry;
use App\Mcp\Prompts\BreakdownEpic;
use App\Mcp\Prompts\PlanSprint;
use App\Mcp\Prompts\WriteIssue;
use App\Mcp\Resources\DiagramsGuide;
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
    'ORIENT BEFORE ANY WRITE: call `current` first. It returns every workspace the '.
    'user can reach. When there is more than one, pass `workspace_slug` explicitly on '.
    'every subsequent call — omitting it silently uses the first membership by id, '.
    "which is often the wrong board.\n\n".
    'Tool names are kebab-case, never dotted. The main ones: current, workspaces-list, issues-list, '.
    'issues-create, issues-update, issues-get, issues-transition, issues-link, '.
    'issues-comment, projects-list, projects-create, projects-get, cycles-list, '.
    'cycles-get, cycles-create, initiatives-list, inbox-list, teams-list, '.
    "labels-list, search.\n\n".
    'Identifiers are TEAMKEY-N, such as LAM-275. Project slugs come from `projects-list`. '.
    "Cycles are addressed as (team_key, number). 'me' resolves to the authenticated ".
    "user.\n\n".
    'Read the resource aims://guides/planning before planning work (object model, '.
    'Scrum mapping, story and estimate conventions), and aims://guides/diagrams '.
    'before writing any plan containing a diagram or chart.'
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
     * Written guides the client can read instead of guessing: the planning
     * methodology (hierarchy vs dependency graph, Scrum mapping, story
     * template) and the plan rendering contract (mermaid / Chart.js markup).
     *
     * Both are `Laravel\Mcp\Server\Resource` subclasses.
     *
     * @var array<int, class-string>
     */
    protected array $resources = [
        PlanningGuide::class,
        DiagramsGuide::class,
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        PlanSprint::class,
        BreakdownEpic::class,
        WriteIssue::class,
    ];

    // Override the package's pagination defaults — we ship 22 tools and
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
