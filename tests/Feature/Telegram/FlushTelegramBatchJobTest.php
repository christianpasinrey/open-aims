<?php

declare(strict_types=1);

use App\Jobs\FlushTelegramBatchJob;
use App\Jobs\SendTelegramMessage;
use App\Models\TelegramBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $fix = makeWorkspaceFixture();
    $this->workspace = $fix['workspace'];
    config(['services.telegram.token' => 'TESTTOKEN']);
});

function makeBatch($workspace, Carbon $flushAt, array $events): TelegramBatch
{
    $batch = TelegramBatch::create([
        'workspace_id' => $workspace->id,
        'chat_id' => '-100WS',
        'first_event_at' => $flushAt->copy()->subSeconds(60),
        'flush_at' => $flushAt,
    ]);
    foreach ($events as $e) {
        $batch->pendingEvents()->create($e);
    }

    return $batch;
}

it('is a no-op when the batch no longer exists', function () {
    Bus::fake();

    (new FlushTelegramBatchJob(999999))->handle();

    Bus::assertNotDispatched(SendTelegramMessage::class);
    Bus::assertNotDispatched(FlushTelegramBatchJob::class);
});

it('reschedules itself when the window has not elapsed yet', function () {
    Bus::fake();
    Carbon::setTestNow('2026-05-30 12:00:00');
    $batch = makeBatch($this->workspace, Carbon::parse('2026-05-30 12:00:40'), [['html' => 'a']]);

    (new FlushTelegramBatchJob($batch->id))->handle();

    Bus::assertNotDispatched(SendTelegramMessage::class);
    Bus::assertDispatched(FlushTelegramBatchJob::class);
    expect(TelegramBatch::find($batch->id))->not->toBeNull();

    Carbon::setTestNow();
});

it('flushes a single event as one message and deletes the batch', function () {
    Bus::fake();
    Carbon::setTestNow('2026-05-30 12:01:00');
    $batch = makeBatch($this->workspace, Carbon::parse('2026-05-30 12:01:00'), [
        ['html' => '🆕 <b>ENG-1</b> — Bug'],
    ]);

    (new FlushTelegramBatchJob($batch->id))->handle();

    Bus::assertDispatched(SendTelegramMessage::class, function ($job) {
        return $job->html === '🆕 <b>ENG-1</b> — Bug' && $job->chatId === '-100WS';
    });
    expect(TelegramBatch::find($batch->id))->toBeNull();

    Carbon::setTestNow();
});

it('flushes multiple events as one combined message', function () {
    Bus::fake();
    Carbon::setTestNow('2026-05-30 12:01:00');
    $batch = makeBatch($this->workspace, Carbon::parse('2026-05-30 12:01:00'), [
        ['html' => '🆕 <b>ENG-1</b> — Bug'],
        ['html' => '👤 <b>ENG-2</b> — Task', 'mention' => '@ana'],
    ]);

    (new FlushTelegramBatchJob($batch->id))->handle();

    Bus::assertDispatched(SendTelegramMessage::class, function ($job) {
        return str_contains($job->html, '📦 <b>2 novedades</b>')
            && str_contains($job->html, '🔔 @ana');
    });

    Carbon::setTestNow();
});

it('does nothing when telegram token is not configured', function () {
    config(['services.telegram.token' => null]);
    Bus::fake();
    Carbon::setTestNow('2026-05-30 12:01:00');
    $batch = makeBatch($this->workspace, Carbon::parse('2026-05-30 12:01:00'), [['html' => 'a']]);

    (new FlushTelegramBatchJob($batch->id))->handle();

    Bus::assertNotDispatched(SendTelegramMessage::class);
    // batch is left intact for when configuration returns
    expect(TelegramBatch::find($batch->id))->not->toBeNull();

    Carbon::setTestNow();
});
