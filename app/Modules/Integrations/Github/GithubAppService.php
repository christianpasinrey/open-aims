<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around the GitHub App REST API.
 *
 * The GitHub App authenticates with two layers:
 *   1. App JWT (RS256) signed with the App's .pem private key — used to
 *      mint installation tokens.
 *   2. Installation token — short-lived (60m) bearer token used for the
 *      actual repo-scoped REST calls.
 *
 * Installation tokens are cached for 50 minutes (10m safety window).
 */
final class GithubAppService
{
    private const API_BASE = 'https://api.github.com';

    private const ACCEPT = 'application/vnd.github+json';

    private const VERSION = '2022-11-28';

    /**
     * Issue a fresh App-level JWT (10m lifetime) signed with the .pem.
     *
     * Returns null if the App is not configured (so callers can fail
     * gracefully on dev/CI without a private key on disk).
     */
    public function appJwt(): ?string
    {
        $appId = config('services.github_app.app_id');
        $keyPath = (string) config('services.github_app.private_key_path');
        if (empty($appId) || $keyPath === '') {
            return null;
        }

        $absolutePath = str_starts_with($keyPath, '/') || preg_match('/^[A-Za-z]:/', $keyPath) === 1
            ? $keyPath
            : base_path($keyPath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $privateKey = file_get_contents($absolutePath);
        if ($privateKey === false || $privateKey === '') {
            return null;
        }

        $now = time();
        $payload = [
            'iat' => $now - 30, // clock skew tolerance
            'exp' => $now + (10 * 60),
            'iss' => (string) $appId,
        ];

        return JWT::encode($payload, $privateKey, 'RS256');
    }

    /**
     * Exchange App JWT → installation token. Cached 50m.
     */
    public function installationToken(int $installationId): ?string
    {
        $cacheKey = 'gh:install:'.$installationId;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $jwt = $this->appJwt();
        if ($jwt === null) {
            return null;
        }

        $url = self::API_BASE."/app/installations/{$installationId}/access_tokens";
        $response = Http::withHeaders([
            'Accept' => self::ACCEPT,
            'X-GitHub-Api-Version' => self::VERSION,
            'Authorization' => 'Bearer '.$jwt,
            'User-Agent' => 'aims-github-app',
        ])->post($url);

        if (! $response->successful()) {
            Log::warning('github-app: failed to mint installation token', [
                'installation_id' => $installationId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $token = (string) $response->json('token', '');
        if ($token === '') {
            return null;
        }

        Cache::put($cacheKey, $token, now()->addMinutes(50));

        return $token;
    }

    /**
     * GET /installation/repositories — list repos the install can see.
     *
     * @return list<array<string,mixed>>
     */
    public function listRepos(int $installationId): array
    {
        $token = $this->installationToken($installationId);
        if ($token === null) {
            return [];
        }

        $repos = [];
        $page = 1;

        do {
            $response = $this->client($token)->get(self::API_BASE.'/installation/repositories', [
                'per_page' => 100,
                'page' => $page,
            ]);
            if (! $response->successful()) {
                Log::warning('github-app: listRepos failed', [
                    'installation_id' => $installationId,
                    'status' => $response->status(),
                ]);
                break;
            }

            /** @var array<string,mixed> $data */
            $data = $response->json();
            $batch = is_array($data['repositories'] ?? null) ? $data['repositories'] : [];
            foreach ($batch as $repo) {
                if (is_array($repo)) {
                    $repos[] = $repo;
                }
            }
            $page++;
        } while (count($batch) === 100);

        return $repos;
    }

    /**
     * GET /repos/{owner}/{repo}/pulls?state=all
     *
     * @return list<array<string,mixed>>
     */
    public function listOpenPulls(int $installationId, string $owner, string $repo): array
    {
        $token = $this->installationToken($installationId);
        if ($token === null) {
            return [];
        }

        $pulls = [];
        $page = 1;

        do {
            $response = $this->client($token)->get(self::API_BASE."/repos/{$owner}/{$repo}/pulls", [
                'state' => 'all',
                'per_page' => 100,
                'page' => $page,
                'sort' => 'updated',
                'direction' => 'desc',
            ]);
            if (! $response->successful()) {
                Log::warning('github-app: listOpenPulls failed', [
                    'installation_id' => $installationId,
                    'owner' => $owner,
                    'repo' => $repo,
                    'status' => $response->status(),
                ]);
                break;
            }

            /** @var list<array<string,mixed>> $batch */
            $batch = (array) $response->json();
            foreach ($batch as $pull) {
                if (is_array($pull)) {
                    $pulls[] = $pull;
                }
            }
            $page++;

            // Cap at 5 pages (500 PRs) to avoid runaway syncs on huge repos.
            if ($page > 5) {
                break;
            }
        } while (count($batch) === 100);

        return $pulls;
    }

    /**
     * GET /repos/{owner}/{repo}/pulls/{number}
     *
     * @return array<string,mixed>|null
     */
    public function getPull(int $installationId, string $owner, string $repo, int $number): ?array
    {
        $token = $this->installationToken($installationId);
        if ($token === null) {
            return null;
        }

        $response = $this->client($token)
            ->get(self::API_BASE."/repos/{$owner}/{$repo}/pulls/{$number}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Constant-time HMAC-SHA256 verification of an `X-Hub-Signature-256`
     * webhook header against the configured webhook secret.
     */
    public function verifyWebhook(string $signature, string $body, string $secret): bool
    {
        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }

    private function client(string $token): PendingRequest
    {
        return Http::withHeaders([
            'Accept' => self::ACCEPT,
            'X-GitHub-Api-Version' => self::VERSION,
            'Authorization' => 'Bearer '.$token,
            'User-Agent' => 'aims-github-app',
        ]);
    }

    public function appName(): string
    {
        $name = config('services.github_app.app_name');

        return is_string($name) ? $name : '';
    }

    public function installUrl(?string $state = null): string
    {
        $configured = (string) config('services.github_app.install_url', '');
        if ($configured === '') {
            $name = $this->appName();
            $configured = $name === ''
                ? 'https://github.com/apps'
                : "https://github.com/apps/{$name}/installations/new";
        }
        if ($state !== null && $state !== '') {
            $sep = str_contains($configured, '?') ? '&' : '?';
            $configured .= $sep.'state='.urlencode($state);
        }

        return $configured;
    }

    public function isConfigured(): bool
    {
        if (! class_exists(JWT::class)) {
            return false;
        }
        $appId = config('services.github_app.app_id');
        if (empty($appId)) {
            return false;
        }
        $keyPath = (string) config('services.github_app.private_key_path');
        if ($keyPath === '') {
            return false;
        }
        $abs = str_starts_with($keyPath, '/') || preg_match('/^[A-Za-z]:/', $keyPath) === 1
            ? $keyPath
            : base_path($keyPath);

        return is_file($abs);
    }

    /**
     * Exposes the configured team→repo mapping so callers can resolve
     * which repo a repo team should pull from.
     *
     * @return array<string,string> // team_key => "owner/repo"
     */
    public function teamRepoMap(): array
    {
        $cfg = config('services.github_app.team_repo_map', []);

        return is_array($cfg)
            ? array_filter($cfg, static fn ($v): bool => is_string($v) && $v !== '')
            : [];
    }

    public function repoForTeamKey(string $teamKey): ?string
    {
        $map = $this->teamRepoMap();

        return $map[strtoupper($teamKey)] ?? null;
    }

    /**
     * Throws if the configured private key is missing. Useful for the
     * sync controller so the user gets a clear 422 instead of a 200 with
     * silent zero results.
     */
    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'GitHub App is not configured. Set GITHUB_APP_ID, GITHUB_APP_NAME, '
                .'GITHUB_APP_WEBHOOK_SECRET and place the .pem at the path indicated '
                .'by GITHUB_APP_PRIVATE_KEY_PATH (defaults to storage/keys/github-app.pem).',
            );
        }
    }
}
