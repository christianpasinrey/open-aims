<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Support\Passport\PersonalTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

/**
 * Personal tokens for Settings → Developer: bearer tokens a person
 * generates by hand for clients that cannot run the OAuth flow (Herdr,
 * scripts). They carry the `mcp` scope and authenticate `/mcp` exactly
 * like an OAuth access token.
 */
final class PersonalTokenController
{
    public function __construct(private readonly PersonalTokens $personalTokens) {}

    public function index(Request $request): JsonResponse
    {
        $tokens = $this->liveTokens($request)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Token $token): array => $this->present($token))
            ->all();

        return response()->json(['tokens' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $this->personalTokens->ensureClient();

        $result = $request->user()->createToken($data['name'], ['mcp']);

        return response()->json([
            ...$this->present($result->getToken()),
            // The only time the secret leaves the server.
            'token' => $result->accessToken,
        ], 201);
    }

    public function destroy(Request $request, string $tokenId): Response
    {
        $token = $this->liveTokens($request)->whereKey($tokenId)->first();
        if ($token === null) {
            abort(404);
        }

        $token->revoke();
        DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $token->getKey())
            ->update(['revoked' => true]);

        return response()->noContent();
    }

    /**
     * The caller's personal tokens that still work.
     *
     * @return Builder<Token>
     */
    private function liveTokens(Request $request): Builder
    {
        return Passport::token()
            ->newQuery()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereIn('client_id', $this->personalTokens->clientIds())
            ->where('revoked', false)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @return array{id:string,name:?string,created_at:?string,expires_at:?string}
     */
    private function present(Token $token): array
    {
        return [
            'id' => (string) $token->getKey(),
            'name' => $token->name,
            'created_at' => $token->created_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ];
    }
}
