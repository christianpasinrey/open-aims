<?php

declare(strict_types=1);

use App\ValueObjects\TelegramUsername;

it('normalizes username and throws for invalid', function () {
    $obj = new TelegramUsername('@alice123');
    expect((string) $obj)->toBe('@alice123');
    expect($obj->toDatabase())->toBe('alice123');

    // valid without @
    $obj2 = new TelegramUsername('bob_1');
    expect((string) $obj2)->toBe('@bob_1');
});

it('throws for invalid usernames', function () {
    new TelegramUsername('ab'); // too short
})->throws(InvalidArgumentException::class);
