<?php

declare(strict_types=1);

use App\Models\TelegramPendingEvent;
use App\Support\Telegram\TelegramBatchMessage;

function pendingEvent(string $html, ?string $mention = null): TelegramPendingEvent
{
    return new TelegramPendingEvent(['html' => $html, 'mention' => $mention]);
}

it('returns the single event html unchanged when there is one event without a mention', function () {
    $messages = TelegramBatchMessage::build(
        collect([pendingEvent('🆕 <b>ENG-1</b> — Bug')]),
        'Engineering',
    );

    expect($messages)->toBe(['🆕 <b>ENG-1</b> — Bug']);
});

it('appends the mention on its own line for a single event', function () {
    $messages = TelegramBatchMessage::build(
        collect([pendingEvent('👤 <b>ENG-1</b> — Bug', '@ana')]),
        'Engineering',
    );

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toBe("👤 <b>ENG-1</b> — Bug\n🔔 @ana");
});

it('combines multiple events under a count header', function () {
    $messages = TelegramBatchMessage::build(
        collect([
            pendingEvent('🆕 <b>ENG-1</b> — Bug'),
            pendingEvent('👤 <b>ENG-2</b> — Task', '@ana'),
        ]),
        'Engineering',
    );

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('📦 <b>2 novedades</b> · Engineering')
        ->and($messages[0])->toContain('🆕 <b>ENG-1</b> — Bug')
        ->and($messages[0])->toContain("👤 <b>ENG-2</b> — Task\n🔔 @ana");
});

it('escapes the workspace name in the header', function () {
    $messages = TelegramBatchMessage::build(
        collect([pendingEvent('a'), pendingEvent('b')]),
        'A & B <x>',
    );

    expect($messages[0])->toContain('A &amp; B &lt;x&gt;')
        ->and($messages[0])->not->toContain('<x>');
});

it('splits into multiple messages when the combined body exceeds 4096 chars', function () {
    $events = collect(range(1, 60))->map(fn ($n) => pendingEvent(str_repeat("line-{$n} ", 20)));

    $messages = TelegramBatchMessage::build($events, 'Engineering');

    expect(count($messages))->toBeGreaterThan(1);
    foreach ($messages as $m) {
        expect(mb_strlen($m))->toBeLessThanOrEqual(4096);
    }
});
