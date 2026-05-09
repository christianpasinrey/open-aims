<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Github;

use App\Modules\Teams\Models\Team;
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
        if (empty($appId)) {
            return null;
        }

        $privateKey = $this->resolvePrivateKey();
        if ($privateKey === null) {
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
     * Pull the App's private key from either the inline env var
     * (GITHUB_APP_PRIVATE_KEY → services.github_app.private_key) or the
     * configured file path. Inline wins. Returns null when neither is set
     * or the file is missing.
     *
     * Inline values commonly come from .env where literal newlines are
     * encoded as \n; we normalise both \r\n and \n forms so the JWT
     * library can parse the PEM regardless.
     */
    private function resolvePrivateKey(): ?string
    {
        $inline = config('services.github_app.private_key');
        if (is_string($inline) && trim($inline) !== '') {
            // .env values lose newlines; if the user pasted "\n" escape
            // sequences, turn them back into real newlines so the PEM
            // parses.
            $pem = str_replace(["\r\n", '\\n'], "\n", $inline);

            return $pem;
        }

        $keyPath = (string) config('services.github_app.private_key_path');
        if ($keyPath === '') {
            return null;
        }

        $absolutePath = str_starts_with($keyPath, '/') || preg_match('/^[A-Za-z]:/', $keyPath) === 1
            ? $keyPath
            : base_path($keyPath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false || $contents === '') {
            return null;
        }

        return $contents;
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
     * GET /repos/{owner}/{repo}/branches — list all branches.
     *
     * Used by the Sync action to backfill `github_branches` for
     * installations that pre-date the App. The REST endpoint does NOT
     * expose the last-commit timestamp, so callers should leave
     * `last_pushed_at` null on backfill — push webhooks fill it in
     * later.
     *
     * Capped at 10 pages (1000 branches): repos with more than that
     * are not worth pulling synchronously.
     *
     * @return list<array<string,mixed>>
     */
    public function listBranches(int $installationId, string $owner, string $repo): array
    {
        $token = $this->installationToken($installationId);
        if ($token === null) {
            return [];
        }

        $branches = [];
        $page = 1;

        do {
            $response = $this->client($token)->get(self::API_BASE."/repos/{$owner}/{$repo}/branches", [
                'per_page' => 100,
                'page' => $page,
            ]);
            if (! $response->successful()) {
                Log::warning('github-app: listBranches failed', [
                    'installation_id' => $installationId,
                    'owner' => $owner,
                    'repo' => $repo,
                    'status' => $response->status(),
                ]);
                break;
            }

            /** @var list<array<string,mixed>> $batch */
            $batch = (array) $response->json();
            foreach ($batch as $branch) {
                if (is_array($branch)) {
                    $branches[] = $branch;
                }
            }
            $page++;

            // Cap at 10 pages (1000 branches) to avoid runaway syncs on
            // huge repos.
            if ($page > 10) {
                break;
            }
        } while (count($batch) === 100);

        return $branches;
    }

    /**
     * GET /app/installations — list every installation of this App. Used
     * by the "Reconcile installations" admin action so we can adopt rows
     * for users who installed via github.com without going through our
     * state-passing /gh/install flow.
     *
     * Returns null when the App isn't configured (so the caller can show
     * a friendly error). Returns [] when configured but the app has no
     * installations.
     *
     * @return list<array<string,mixed>>|null
     */
    public function listInstallations(): ?array
    {
        $jwt = $this->appJwt();
        if ($jwt === null) {
            return null;
        }

        $out = [];
        $page = 1;
        do {
            $response = Http::withHeaders([
                'Accept' => self::ACCEPT,
                'X-GitHub-Api-Version' => self::VERSION,
                'Authorization' => 'Bearer '.$jwt,
                'User-Agent' => 'aims-github-app',
            ])->get(self::API_BASE.'/app/installations', [
                'per_page' => 100,
                'page' => $page,
            ]);
            if (! $response->successful()) {
                Log::warning('github-app: listInstallations failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            /** @var list<array<string,mixed>> $batch */
            $batch = (array) $response->json();
            foreach ($batch as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }
            $page++;
            // App rarely has more than a handful of installs; cap at 5
            // pages anyway so we never spin.
            if ($page > 5) {
                break;
            }
        } while (count($batch) === 100);

        return $out;
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

        // Either an inline env PEM or a real file on disk satisfies us.
        return $this->resolvePrivateKey() !== null;
    }

    /**
     * Exposes the configured team→repo mapping. Now sourced from the
     * `teams.github_repo_full_name` column (UI editable per team), with
     * the legacy `services.github_app.team_repo_map` config kept as a
     * fallback so existing deploys keep working.
     *
     * @return array<string,string> // team_key => "owner/repo"
     */
    public function teamRepoMap(): array
    {
        $fromConfig = config('services.github_app.team_repo_map', []);
        $map = is_array($fromConfig)
            ? array_filter($fromConfig, static fn ($v): bool => is_string($v) && $v !== '')
            : [];

        // Override / extend with whatever workspaces have set in the DB.
        try {
            $rows = Team::query()
                ->withoutGlobalScopes()
                ->whereNotNull('github_repo_full_name')
                ->get(['key', 'github_repo_full_name']);
            foreach ($rows as $row) {
                $map[strtoupper((string) $row->key)] = (string) $row->github_repo_full_name;
            }
        } catch (\Throwable) {
            // DB unavailable (early boot, migrations not run, …) — fall back
            // to whatever the config provided.
        }

        return $map;
    }

    public function repoForTeamKey(string $teamKey): ?string
    {
        $key = strtoupper($teamKey);

        // Prefer the per-team column if present, else fall back to config.
        try {
            $row = Team::query()
                ->withoutGlobalScopes()
                ->where('key', $key)
                ->whereNotNull('github_repo_full_name')
                ->value('github_repo_full_name');
            if (is_string($row) && $row !== '') {
                return $row;
            }
        } catch (\Throwable) {
            // ignore — config fallback
        }

        $map = is_array(config('services.github_app.team_repo_map'))
            ? config('services.github_app.team_repo_map')
            : [];

        $val = $map[$key] ?? null;

        return is_string($val) && $val !== '' ? $val : null;
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
