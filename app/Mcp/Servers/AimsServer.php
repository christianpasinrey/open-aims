<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Core\Contracts\ProvidesMcpTools;
use App\Core\Registries\ModuleRegistry;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Aims')]
#[Version('1.0.0')]
#[Instructions(
    'Operate an AIMS workspace from natural language. '.
    "All tools are scoped to the authenticated user's active workspace.\n\n".
    'Quick start: call workspace.current to discover team keys (e.g. LAM), '.
    'then issues.list / issues.create / issues.transition for issue work, '.
    'projects.list / projects.create for project work, cycles.list / '.
    "cycles.get for sprint/cycle work. inbox.list returns the user's ".
    "personal feed of assignments, comments, and updates.\n\n".
    'Identifiers follow the TEAMKEY-N convention (e.g. LAM-275). '.
    'Project slugs come from projects.list. Cycles are addressed as '.
    "(team_key, number).\n\n".
    "When the user says 'me', tools resolve to the authenticated user."
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

    protected array $resources = [];

    protected array $prompts = [];

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
