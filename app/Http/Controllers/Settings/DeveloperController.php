<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
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

        // Active MCP tokens grouped by OAuth client. Each client row
        // represents one "device" that authorised the workspace; if the
        // same Claude Desktop install reauthorised five times you'll see
        // five separate clients (Claude regenerates the registration each
        // time it loses local state) — that's why we surface the client
        // name + redirect_uri so you can tell them apart and revoke
        // stale entries.
        $clients = collect();
        if ($user !== null) {
            $rows = DB::table('oauth_access_tokens as t')
                ->leftJoin('oauth_clients as c', 'c.id', '=', 't.client_id')
                ->leftJoin('oauth_client_metadata as m', function ($join) use ($user) {
                    $join->on('m.client_id', '=', 't.client_id')
                        ->where('m.user_id', $user->getAuthIdentifier());
                })
                ->where('t.user_id', $user->getAuthIdentifier())
                ->where('t.revoked', false)
                ->where(function ($q) {
                    $q->whereNull('t.expires_at')
                        ->orWhere('t.expires_at', '>', now());
                })
                ->orderByDesc('t.created_at')
                ->get([
                    't.id as token_id',
                    't.client_id',
                    't.name as token_name',
                    't.scopes',
                    't.created_at',
                    't.expires_at',
                    'c.name as client_name',
                    'c.redirect_uris',
                    'm.platform',
                    'm.browser',
                    'm.ip',
                    'm.user_agent',
                ]);

            $clients = $rows
                ->map(function ($row) {
                    $scopes = is_string($row->scopes) ? json_decode($row->scopes, true) : ($row->scopes ?? []);
                    $row->scopes = is_array($scopes) ? $scopes : [];

                    return $row;
                })
                ->filter(fn ($r) => in_array('mcp', $r->scopes, true) || in_array('mcp:use', $r->scopes, true))
                ->groupBy('client_id')
                ->map(function ($group) {
                    $latest = $group->first();
                    $redirects = is_string($latest->redirect_uris)
                        ? (json_decode($latest->redirect_uris, true) ?? [])
                        : ($latest->redirect_uris ?? []);
                    $primaryRedirect = is_array($redirects) ? ($redirects[0] ?? null) : null;

                    return [
                        'client_id' => $latest->client_id,
                        'name' => $latest->client_name ?? $latest->token_name ?? 'Unnamed client',
                        'kind' => $this->classifyClient($latest->client_name, $primaryRedirect, $latest->browser ?? null),
                        'redirect_uri' => $primaryRedirect,
                        'platform' => $latest->platform,
                        'browser' => $latest->browser,
                        'ip' => $latest->ip,
                        'token_count' => $group->count(),
                        'scopes' => $latest->scopes,
                        'last_authorised_at' => $latest->created_at,
                    ];
                })
                ->values();
        }

        return Inertia::render('settings/Developer', [
            'mcp' => [
                'endpoint' => $appUrl.'/mcp',
                'oauth_authorize' => $appUrl.'/oauth/authorize',
                'oauth_token' => $appUrl.'/oauth/token',
                'clients' => $clients->all(),
                'connected' => $clients->isNotEmpty(),
                'status' => $clients->isNotEmpty() ? 'connected' : 'not_connected',
            ],
        ]);
    }

    public function revokeClient(Request $request, string $clientId): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $tokenIds = DB::table('oauth_access_tokens')
            ->where('user_id', $user->getAuthIdentifier())
            ->where('client_id', $clientId)
            ->pluck('id');

        DB::table('oauth_access_tokens')->whereIn('id', $tokenIds)->update(['revoked' => true]);
        DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);

        return redirect()->route('settings.developer')->with('status', 'mcp-client-revoked');
    }

    /**
     * Keep only the most recent token for a given client (and the
     * matching refresh token); revoke every older one. Useful when a
     * single Claude Desktop install has reauthorised many times and
     * left a pile of stale tokens.
     */
    public function keepLatestForClient(Request $request, string $clientId): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $tokens = DB::table('oauth_access_tokens')
            ->where('user_id', $user->getAuthIdentifier())
            ->where('client_id', $clientId)
            ->where('revoked', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id');

        $stale = $tokens->slice(1)->values();
        if ($stale->isNotEmpty()) {
            DB::table('oauth_access_tokens')->whereIn('id', $stale)->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $stale)->update(['revoked' => true]);
        }

        return redirect()->route('settings.developer')->with('status', 'mcp-client-deduped');
    }

    /**
     * Across the user's MCP tokens, keep only the latest per client_id
     * and revoke everything else. Convenience for the "I authorised five
     * times by mistake from the same device" case.
     */
    public function keepLatestPerClient(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $perClient = DB::table('oauth_access_tokens')
            ->where('user_id', $user->getAuthIdentifier())
            ->where('revoked', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'client_id'])
            ->groupBy('client_id');

        $stale = collect();
        foreach ($perClient as $group) {
            $stale = $stale->merge($group->slice(1)->pluck('id'));
        }
        if ($stale->isNotEmpty()) {
            DB::table('oauth_access_tokens')->whereIn('id', $stale)->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $stale)->update(['revoked' => true]);
        }

        return redirect()->route('settings.developer')->with('status', 'mcp-clients-deduped');
    }

    /**
     * Classify a connected OAuth client by name + redirect URI so the UI
     * can show a meaningful "Claude Desktop" vs "Claude Code" vs "Other"
     * label even when the dynamic-client-registration name is generic.
     */
    private function classifyClient(?string $name, ?string $redirect, ?string $browser = null): string
    {
        $haystack = strtolower(($name ?? '').' '.($redirect ?? '').' '.($browser ?? ''));
        if (str_contains($haystack, 'claude.ai')) {
            return 'Claude (web)';
        }
        if (str_contains($haystack, 'claude://') || $browser === 'Claude') {
            return 'Claude Desktop';
        }
        if (str_contains($haystack, 'localhost') || str_contains($haystack, '127.0.0.1')) {
            return 'Claude Code (local)';
        }
        if (str_contains($haystack, 'cursor://') || str_contains($haystack, 'cursor')) {
            return 'Cursor';
        }
        if (str_contains($haystack, 'vscode://') || str_contains($haystack, 'vscode')) {
            return 'VS Code';
        }

        return 'MCP client';
    }
}
