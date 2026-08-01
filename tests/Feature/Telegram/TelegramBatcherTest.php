<?php

declare(strict_types=1);

use App\Jobs\FlushTelegramBatchJob;
use App\Models\TelegramBatch;
use App\Support\Telegram\TelegramBatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->workspace = $fix['workspace'];
    config(['services.telegram.batch_window' => 60, 'services.telegram.batch_max_wait' => 300]);
});

it('creates a batch and a pending event without sending immediately', function () {
    Bus::fake();

    app(TelegramBatcher::class)->enqueue($this->workspace->id, '-100WS', '🆕 evento', '@ana');

    $batch = TelegramBatch::where('workspace_id', $this->workspace->id)->first();
    expect($batch)->not->toBeNull()
        ->and($batch->chat_id)->toBe('-100WS')
        ->and($batch->pendingEvents)->toHaveCount(1)
        ->and($batch->pendingEvents->first()->mention)->toBe('@ana');

    Bus::assertDispatched(FlushTelegramBatchJob::class);
});

it('sets flush_at to now + window for the first event', function () {
    Bus::fake();
    Carbon::setTestNow('2026-05-30 12:00:00');

    app(TelegramBatcher::class)->enqueue($this->workspace->id, '-100WS', 'a');

    $batch = TelegramBatch::where('workspace_id', $this->workspace->id)->first();
    expect($batch->flush_at->toDateTimeString())->toBe('2026-05-30 12:01:00')
        ->and($batch->first_event_at->toDateTimeString())->toBe('2026-05-30 12:00:00');

    Carbon::setTestNow();
});

it('resets flush_at to now + window on a second event within the window', function () {
    Bus::fake();
    Carbon::setTestNow('2026-05-30 12:00:00');
    app(TelegramBatcher::class)->enqueue($this->workspace->id, '-100WS', 'a');

    Carbon::setTestNow('2026-05-30 12:00:30');
    app(TelegramBatcher::class)->enqueue($this->workspace->id, '-100WS', 'b');

    $batch = TelegramBatch::where('workspace_id', $this->workspace->id)->first();
    expect($batch->flush_at->toDateTimeString())->toBe('2026-05-30 12:01:30')
        ->and($batch->pendingEvents)->toHaveCount(2);

    Carbon::setTestNow();
});

it('caps flush_at at first_event_at + max_wait under a continuous stream', function () {
    Bus::fake();
    Carbon::setTestNow('2026-05-30 12:00:00');
    app(TelegramBatcher::class)->enqueue($this->workspace->id, '-100WS', 'a');

    // 4m50s after the first event: now + 60s would be 12:05:50, but the cap is 12:05:00.
    Carbon::setTestNow('2026-05-30 12:04:50');
    app(TelegramBatcher::class)->enqueue($this->workspace->id, '-100WS', 'b');

    $batch = TelegramBatch::where('workspace_id', $this->workspace->id)->first();
    expect($batch->flush_at->toDateTimeString())->toBe('2026-05-30 12:05:00');

    Carbon::setTestNow();
});
