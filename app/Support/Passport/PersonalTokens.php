<?php

declare(strict_types=1);

namespace App\Support\Passport;

use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use RuntimeException;

/**
 * The Passport client behind personal tokens (Settings → Developer).
 *
 * Every personal token of every user hangs off one client, so code that
 * groups or prunes tokens per OAuth client has to leave these ids out.
 */
final class PersonalTokens
{
    public const CLIENT_NAME = 'AIMS Personal Tokens';

    public function __construct(private readonly ClientRepository $clients) {}

    /**
     * Create the personal access client if the instance has none, so no
     * `passport:client --personal` step is needed on deploy.
     */
    public function ensureClient(): void
    {
        $provider = (string) config('auth.guards.api.provider');

        try {
            $this->clients->personalAccessClient($provider);
        } catch (RuntimeException) {
            $this->clients->createPersonalAccessGrantClient(self::CLIENT_NAME, $provider);
        }
    }

    /**
     * Ids of every client that can issue personal tokens. Never creates one.
     *
     * @return list<string>
     */
    public function clientIds(): array
    {
        return Passport::client()
            ->newQuery()
            ->get()
            ->filter(fn (Client $client): bool => $client->hasGrantType('personal_access'))
            ->map(fn (Client $client): string => (string) $client->getKey())
            ->values()
            ->all();
    }
}
