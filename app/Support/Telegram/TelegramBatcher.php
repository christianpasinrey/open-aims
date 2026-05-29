<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use App\Jobs\FlushTelegramBatchJob;
use App\Models\TelegramBatch;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Buffers a formatted Telegram event into the per-workspace batch and (re)schedules
 * the debounce flush. The window resets to now + batch_window on every event, capped
 * at first_event_at + batch_max_wait.
 */
final class TelegramBatcher
{
    public function enqueue(int $workspaceId, string $chatId, string $html, ?string $mention = null): void
    {
        $window = (int) config('services.telegram.batch_window', 60);
        $maxWait = (int) config('services.telegram.batch_max_wait', 300);
        $now = now();

        $batch = DB::transaction(function () use ($workspaceId, $chatId, $html, $mention, $window, $maxWait, $now): TelegramBatch {
            $batch = $this->lockBatch($workspaceId);

            if ($batch === null) {
                $batch = $this->createBatch($workspaceId, $chatId, $now->copy()->addSeconds($window), $now);
            } else {
                $cap = $batch->first_event_at->copy()->addSeconds($maxWait);
                $candidate = $now->copy()->addSeconds($window);
                $batch->flush_at = $candidate->lessThan($cap) ? $candidate : $cap;
                $batch->chat_id = $chatId;
                $batch->save();
            }

            $batch->pendingEvents()->create(['html' => $html, 'mention' => $mention]);

            return $batch;
        });

        FlushTelegramBatchJob::dispatch($batch->id)
            ->delay($batch->flush_at)
            ->afterCommit();
    }

    private function lockBatch(int $workspaceId): ?TelegramBatch
    {
        return TelegramBatch::query()
            ->where('workspace_id', $workspaceId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Create the batch, tolerating a concurrent insert (unique workspace_id):
     * if another process won the race, re-fetch its row with a lock instead of
     * throwing — an exception here would abort the user's originating action.
     */
    private function createBatch(int $workspaceId, string $chatId, CarbonInterface $flushAt, CarbonInterface $firstEventAt): TelegramBatch
    {
        try {
            return TelegramBatch::create([
                'workspace_id' => $workspaceId,
                'chat_id' => $chatId,
                'first_event_at' => $firstEventAt,
                'flush_at' => $flushAt,
            ]);
        } catch (QueryException) {
            return $this->lockBatch($workspaceId) ?? throw new RuntimeException('telegram batch race could not be resolved');
        }
    }
}
