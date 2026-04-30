<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Developer page. Surfaces the MCP / API connection details
 * so users (and their teammates) can wire aims into Claude
 * Desktop, Claude Code, and other agentic clients.
 */
final class DeveloperController
{
    public function show(Request $request): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $user = $request->user();

        // Detect MCP connections by looking for non-revoked, non-expired
        // Passport tokens for this user that include the `mcp` scope.
        // The package issues `mcp` and `mcp:use` interchangeably; either
        // counts as connected.
        $tokensRaw = collect();
        if ($user !== null) {
            $tokensRaw = DB::table('oauth_access_tokens')
                ->where('user_id', $user->getAuthIdentifier())
                ->where('revoked', false)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'scopes', 'client_id', 'created_at', 'expires_at']);
        }

        $tokens = $tokensRaw
            ->map(function ($row) {
                $scopes = is_string($row->scopes) ? json_decode($row->scopes, true) : ($row->scopes ?? []);
                $scopes = is_array($scopes) ? $scopes : [];
                $clientName = DB::table('oauth_clients')->where('id', $row->client_id)->value('name');

                return [
                    'name' => $row->name ?? $clientName ?? 'Unnamed client',
                    'scopes' => $scopes,
                    'created_at' => $row->created_at,
                    'expires_at' => $row->expires_at,
                    'is_mcp' => in_array('mcp', $scopes, true) || in_array('mcp:use', $scopes, true),
                ];
            })
            ->filter(fn (array $t) => $t['is_mcp']);

        return Inertia::render('settings/Developer', [
            'mcp' => [
                'endpoint' => $appUrl.'/mcp',
                'oauth_authorize' => $appUrl.'/oauth/authorize',
                'oauth_token' => $appUrl.'/oauth/token',
                'tokens' => $tokens->values()->map(fn ($t, $i) => [
                    'id' => $i + 1,
                    'name' => $t['name'],
                    'scopes' => $t['scopes'],
                    'last_used_at' => $t['created_at'],
                ])->all(),
                'connected' => $tokens->isNotEmpty(),
                'status' => $tokens->isNotEmpty() ? 'connected' : 'not_connected',
            ],
        ]);
    }
}
