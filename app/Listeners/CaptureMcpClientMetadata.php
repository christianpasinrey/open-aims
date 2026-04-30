<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Events\AccessTokenCreated;

/**
 * When Passport issues a new access token, capture the originating
 * request's User-Agent and IP and tag the OAuth client with it. This
 * lets the /settings/developer page show "Claude Desktop · macOS" vs
 * "Claude Desktop · Windows" so users can revoke a specific device
 * without losing all of them.
 *
 * Idempotent per (user_id, client_id) — re-authorising the same client
 * from the same device just refreshes the metadata row.
 */
final class CaptureMcpClientMetadata
{
    public function __construct(private readonly Request $request)
    {
    }

    public function handle(AccessTokenCreated $event): void
    {
        if ($event->userId === null || $event->userId === '') {
            return;
        }

        $userAgent = (string) $this->request->userAgent();
        if ($userAgent === '') {
            return;
        }

        DB::table('oauth_client_metadata')->updateOrInsert(
            [
                'user_id' => (int) $event->userId,
                'client_id' => (string) $event->clientId,
            ],
            [
                'user_agent' => mb_substr($userAgent, 0, 500),
                'platform' => $this->detectPlatform($userAgent),
                'browser' => $this->detectBrowser($userAgent),
                'ip' => (string) $this->request->ip(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function detectPlatform(string $ua): string
    {
        $ua = strtolower($ua);
        if (str_contains($ua, 'mac os x') || str_contains($ua, 'macintosh')) return 'macOS';
        if (str_contains($ua, 'windows')) return 'Windows';
        if (str_contains($ua, 'iphone')) return 'iOS';
        if (str_contains($ua, 'ipad')) return 'iPadOS';
        if (str_contains($ua, 'android')) return 'Android';
        if (str_contains($ua, 'linux')) return 'Linux';

        return 'Unknown';
    }

    private function detectBrowser(string $ua): string
    {
        $ua = strtolower($ua);
        // Specific clients first.
        if (str_contains($ua, 'claude')) return 'Claude';
        if (str_contains($ua, 'cursor')) return 'Cursor';
        if (str_contains($ua, 'vscode')) return 'VS Code';
        if (str_contains($ua, 'edg/')) return 'Edge';
        if (str_contains($ua, 'firefox')) return 'Firefox';
        if (str_contains($ua, 'chrome')) return 'Chrome';
        if (str_contains($ua, 'safari')) return 'Safari';

        return 'Other';
    }
}
