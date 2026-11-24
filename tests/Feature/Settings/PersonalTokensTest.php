<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Passport\PersonalTokens;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    usePassportTestKeys();
});

/**
 * Log in as a user the way a separate browser would: the test session is
 * shared between requests, and it remembers the previous user's workspace.
 */
function actingAsInOwnSession(User $user)
{
    app('session')->forget('current_workspace_id');

    return test()->actingAs($user);
}

/**
 * Create a token through the endpoint and return its JSON.
 *
 * @return array{id:string,name:string,token:string,created_at:string,expires_at:?string}
 */
function generatePersonalToken(User $user, string $name = 'Herdr laptop'): array
{
    return actingAsInOwnSession($user)
        ->postJson(route('settings.developer.tokens.store'), ['name' => $name])
        ->assertCreated()
        ->json();
}

/** Call /mcp with a bearer token, as an external client would. */
function callMcpWithBearer(string $token)
{
    // Drop the session login so only the bearer authenticates the call.
    auth()->forgetGuards();
    app('session')->flush();

    return test()->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'current', 'arguments' => (object) []],
    ]);
}

it('generates a personal token that authenticates /mcp', function () {
    $fix = makeWorkspaceFixture();

    $created = $this->actingAs($fix['user'])
        ->postJson(route('settings.developer.tokens.store'), ['name' => 'Herdr laptop'])
        ->assertCreated()
        ->assertJsonStructure(['id', 'name', 'token', 'created_at', 'expires_at'])
        ->json();

    expect($created['name'])->toBe('Herdr laptop')
        ->and($created['token'])->toBeString()->not->toBe('');

    callMcpWithBearer($created['token'])
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});

it('creates the personal access client on first use and only once', function () {
    $fix = makeWorkspaceFixture();

    expect(app(PersonalTokens::class)->clientIds())->toBe([]);

    generatePersonalToken($fix['user'], 'a');
    generatePersonalToken($fix['user'], 'b');

    expect(app(PersonalTokens::class)->clientIds())->toHaveCount(1);
});

it('lists only the caller\'s live tokens without the secret', function () {
    $fix = makeWorkspaceFixture();
    // A second member with a workspace of their own (the web stack requires one).
    $other = makeWorkspaceFixture(['team' => ['key' => 'OPS']])['user'];

    $mine = generatePersonalToken($fix['user'], 'mine');
    generatePersonalToken($other, 'theirs');

    $tokens = actingAsInOwnSession($fix['user'])
        ->getJson(route('settings.developer.tokens.index'))
        ->assertOk()
        ->json('tokens');

    expect($tokens)->toHaveCount(1)
        ->and(array_keys($tokens[0]))->toBe(['id', 'name', 'created_at', 'expires_at'])
        ->and($tokens[0]['id'])->toBe($mine['id'])
        ->and($tokens[0]['name'])->toBe('mine')
        ->and($tokens[0]['expires_at'])->not->toBeNull();
});

it('revokes a token so /mcp rejects it', function () {
    $fix = makeWorkspaceFixture();
    $created = generatePersonalToken($fix['user']);

    $this->actingAs($fix['user'])
        ->deleteJson(route('settings.developer.tokens.destroy', ['tokenId' => $created['id']]))
        ->assertNoContent();

    expect($this->actingAs($fix['user'])
        ->getJson(route('settings.developer.tokens.index'))
        ->json('tokens'))->toBe([]);

    callMcpWithBearer($created['token'])->assertUnauthorized();
});

it('answers 404 when revoking another user\'s token and leaves it live', function () {
    $fix = makeWorkspaceFixture();
    $intruder = makeWorkspaceFixture(['team' => ['key' => 'OPS']])['user'];
    $created = generatePersonalToken($fix['user']);

    actingAsInOwnSession($intruder)
        ->deleteJson(route('settings.developer.tokens.destroy', ['tokenId' => $created['id']]))
        ->assertNotFound();

    expect(actingAsInOwnSession($fix['user'])
        ->getJson(route('settings.developer.tokens.index'))
        ->json('tokens'))->toHaveCount(1);
});

it('answers 404 when revoking an unknown token id', function () {
    $fix = makeWorkspaceFixture();

    $this->actingAs($fix['user'])
        ->deleteJson(route('settings.developer.tokens.destroy', ['tokenId' => 'nope']))
        ->assertNotFound();
});

it('rejects an invalid name', function (string $name) {
    $fix = makeWorkspaceFixture();

    $this->actingAs($fix['user'])
        ->postJson(route('settings.developer.tokens.store'), ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with([
    'empty' => [''],
    'whitespace only' => ['   '],
    '61 characters' => [str_repeat('x', 61)],
]);

it('trims the name', function () {
    $fix = makeWorkspaceFixture();

    expect(generatePersonalToken($fix['user'], '  Herdr  ')['name'])->toBe('Herdr');
});

it('allows two tokens with the same name', function () {
    $fix = makeWorkspaceFixture();

    generatePersonalToken($fix['user'], 'same');
    generatePersonalToken($fix['user'], 'same');

    expect($this->actingAs($fix['user'])
        ->getJson(route('settings.developer.tokens.index'))
        ->json('tokens'))->toHaveCount(2);
});

it('throttles generation', function () {
    $fix = makeWorkspaceFixture();

    foreach (range(1, 10) as $i) {
        generatePersonalToken($fix['user'], 'token '.$i);
    }

    $this->actingAs($fix['user'])
        ->postJson(route('settings.developer.tokens.store'), ['name' => 'one too many'])
        ->assertTooManyRequests();
});

it('requires a login', function () {
    $this->postJson(route('settings.developer.tokens.store'), ['name' => 'x'])->assertUnauthorized();
    $this->getJson(route('settings.developer.tokens.index'))->assertUnauthorized();
});

it('keeps personal tokens out of the connected clients list', function () {
    $fix = makeWorkspaceFixture();
    generatePersonalToken($fix['user']);

    $this->actingAs($fix['user'])
        ->get(route('settings.developer'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('mcp.clients', []));
});

it('keeps every personal token when deduping clients', function () {
    $fix = makeWorkspaceFixture();
    generatePersonalToken($fix['user'], 'a');
    generatePersonalToken($fix['user'], 'b');

    $this->actingAs($fix['user'])->post(route('settings.developer.dedupe'))->assertRedirect();

    expect($this->actingAs($fix['user'])
        ->getJson(route('settings.developer.tokens.index'))
        ->json('tokens'))->toHaveCount(2);
});

it('refuses keep-latest on the personal client and keeps every token', function () {
    $fix = makeWorkspaceFixture();
    generatePersonalToken($fix['user'], 'a');
    generatePersonalToken($fix['user'], 'b');
    $clientId = app(PersonalTokens::class)->clientIds()[0];

    $this->actingAs($fix['user'])
        ->post(route('settings.developer.client.keep-latest', ['clientId' => $clientId]))
        ->assertNotFound();

    expect($this->actingAs($fix['user'])
        ->getJson(route('settings.developer.tokens.index'))
        ->json('tokens'))->toHaveCount(2);
});
