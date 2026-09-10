<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Exceptions;

use InvalidArgumentException;

/**
 * A graph payload broke the schema. The message lists every problem with a
 * readable path (`references[update].purpose`, `edges.0.to`) so an MCP client
 * can fix the whole payload in one go.
 */
final class InvalidGraphPayload extends InvalidArgumentException
{
    /**
     * @param  list<string>  $problems
     */
    public static function because(array $problems): self
    {
        return new self('Invalid graph: '.implode(' ', $problems));
    }
}
