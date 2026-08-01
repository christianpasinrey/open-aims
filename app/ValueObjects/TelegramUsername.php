<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Value object representing a Telegram username.
 *
 * Stored without the leading @ and rendered with it, so the column holds one
 * canonical form however the handle was typed. The accepted shape mirrors
 * ProfileValidationRules::telegramUsernameRules() — keep the two in step, or the
 * profile form and this object will disagree about what is valid.
 */
final class TelegramUsername implements Castable, JsonSerializable, Stringable
{
    /** The normalized telegram handle (without leading @). */
    private string $handle;

    public function __construct(string $value)
    {
        $trimmed = ltrim(trim($value), '@');

        if (preg_match('/^[A-Za-z0-9_]{5,32}$/', $trimmed) !== 1) {
            throw new InvalidArgumentException("Invalid Telegram username: {$value}");
        }

        $this->handle = $trimmed;
    }

    /** @param  array<int,mixed>  $arguments */
    public static function castUsing(array $arguments): string
    {
        return TelegramUsernameCast::class;
    }

    public function __toString(): string
    {
        return '@'.$this->handle;
    }

    /**
     * Serialize as the rendered handle, not as an object. The profile page reads
     * `user.telegram_username` straight out of the Inertia payload, and without
     * this it would receive `{}` and render an empty field.
     */
    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /** Value suitable for database storage (no leading @). */
    public function toDatabase(): string
    {
        return $this->handle;
    }
}
