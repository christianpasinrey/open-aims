<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use Laravel\Mcp\Server\Tool;

/**
 * Optional companion to ModuleManifest. Modules that ship MCP tools
 * implement this interface on their manifest and the server picks them
 * up automatically via the ModuleRegistry — no central tool list to
 * keep in sync.
 */
interface ProvidesMcpTools
{
    /**
     * Tool class strings exposed to MCP clients for this module. The
     * order is the order they will appear in `tools/list`.
     *
     * @return list<class-string<Tool>>
     */
    public function mcpTools(): array;
}
