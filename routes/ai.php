<?php

declare(strict_types=1);

use App\Mcp\Servers\AimsServer;
use Laravel\Mcp\Facades\Mcp;

// OAuth 2.1 discovery endpoints (.well-known/oauth-protected-resource and
// .well-known/oauth-authorization-server) plus the dynamic client
// registration endpoint at /oauth/register. These let Claude Desktop
// discover and self-register against the workspace.
Mcp::oauthRoutes();

// Public MCP endpoint — protected by Passport bearer auth + the `mcp`
// scope (see Passport scope registration in AppServiceProvider).
Mcp::web('/mcp', AimsServer::class)->middleware(['auth:api']);
