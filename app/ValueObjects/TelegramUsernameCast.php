<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts the `telegram_username` column to a TelegramUsername value object.
 *
 * Assigning an invalid handle throws from the value object's constructor rather
 * than reaching the database, so an unusable handle cannot be persisted by a
 * code path that skipped form validation (a seeder, a console command, the MCP).
 *
 * @implements CastsAttributes<TelegramUsername|null, TelegramUsername|string|null>
 */
final class TelegramUsernameCast implements CastsAttributes
{
    /**
     * @param  array<string,mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TelegramUsername
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new TelegramUsername((string) $value);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof TelegramUsername) {
            return $value->toDatabase();
        }

        return (new TelegramUsername((string) $value))->toDatabase();
    }
}
