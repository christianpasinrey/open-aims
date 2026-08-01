<?php

declare(strict_types=1);

use App\Models\User;
use App\ValueObjects\TelegramUsername;

it('casts telegram_username correctly', function () {
    $user = User::factory()->create(['telegram_username' => 'alice_123']);
    expect($user->telegram_username)->toBeInstanceOf(TelegramUsername::class);
    expect((string) $user->telegram_username)->toBe('@alice_123');

    // underlying db value
    $raw = User::find($user->id)->getAttributes()['telegram_username'];
    expect($raw)->toBe('alice_123');
});

it('handles null values', function () {
    $user = User::factory()->create(['telegram_username' => null]);
    expect($user->telegram_username)->toBeNull();
});

it('sets via cast and validates', function () {
    $user = User::factory()->create();
    // A leading @ is accepted on the way in and stripped for storage. The handle
    // is 5+ chars because that is the floor both the value object and
    // ProfileValidationRules::telegramUsernameRules() enforce.
    $user->telegram_username = '@bob_99';
    $user->save();

    $fresh = User::find($user->id);

    expect((string) $fresh->telegram_username)->toBe('@bob_99')
        ->and($fresh->getAttributes()['telegram_username'])->toBe('bob_99');
});

it('rejects a handle shorter than the minimum', function () {
    $user = User::factory()->create();
    $user->telegram_username = '@bob';
})->throws(InvalidArgumentException::class);

it('throws on invalid set', function () {
    $user = User::factory()->create();
    // directly assign invalid value
    $user->telegram_username = 'bad!';
})->throws(InvalidArgumentException::class);
