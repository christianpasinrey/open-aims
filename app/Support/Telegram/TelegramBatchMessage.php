<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use App\Models\TelegramPendingEvent;
use Illuminate\Support\Collection;

/**
 * Builds the HTML message(s) for a flushed Telegram batch.
 *
 * One pending event renders exactly as it did before batching existed (plus an
 * optional mention line). Two or more render under a "N novedades" header.
 * The result is an array because a large batch may exceed Telegram's 4096-char
 * per-message limit and must be split.
 */
final class TelegramBatchMessage
{
    private const LIMIT = 4096;

    /**
     * @param  Collection<int,TelegramPendingEvent>  $events
     * @return array<int,string>
     */
    public static function build(Collection $events, ?string $workspaceName): array
    {
        $blocks = $events
            ->map(fn (TelegramPendingEvent $e): string => self::renderBlock($e))
            ->all();

        if (count($blocks) <= 1) {
            return $blocks === [] ? [] : [$blocks[0]];
        }

        $header = '📦 <b>'.count($blocks).' novedades</b> · '.self::e((string) $workspaceName);

        return self::pack($header, $blocks);
    }

    private static function renderBlock(TelegramPendingEvent $event): string
    {
        $html = (string) $event->html;

        return $event->mention !== null && $event->mention !== ''
            ? $html."\n🔔 ".$event->mention
            : $html;
    }

    /**
     * Greedily pack blocks (separated by a blank line) into messages under the
     * 4096 limit. The header rides on the first message.
     *
     * @param  array<int,string>  $blocks
     * @return array<int,string>
     */
    private static function pack(string $header, array $blocks): array
    {
        $messages = [];
        $current = $header;

        foreach ($blocks as $block) {
            $candidate = $current."\n\n".$block;
            if (mb_strlen($candidate) > self::LIMIT) {
                $messages[] = $current;
                $current = $block;
            } else {
                $current = $candidate;
            }
        }

        $messages[] = $current;

        return $messages;
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
