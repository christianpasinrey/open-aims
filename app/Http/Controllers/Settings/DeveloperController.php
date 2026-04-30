<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Developer page. Surfaces the MCP / API connection details
 * so users (and their teammates) can wire aims into Claude
 * Desktop, Claude Code, and other agentic clients.
 *
 * The full MCP server build is in flight — for now this page documents
 * the URL + planned tools so the navigation entry doesn't 404.
 */
final class DeveloperController
{
    public function show(Request $request): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        return Inertia::render('settings/Developer', [
            'mcp' => [
                'endpoint' => $appUrl.'/mcp',
                'oauth_authorize' => $appUrl.'/oauth/authorize',
                'oauth_token' => $appUrl.'/oauth/token',
                // No tokens persisted yet — the full Passport-backed
                // personal access token UI lands with the next agent run.
                'tokens' => [],
                'connected' => false,
                'status' => 'not_configured',
            ],
        ]);
    }
}
