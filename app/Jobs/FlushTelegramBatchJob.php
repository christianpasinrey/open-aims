<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TelegramBatch;
use App\Modules\Workspaces\Models\Workspace;
use App\Support\Telegram\TelegramBatchMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Debounced flush of a per-workspace Telegram batch.
 *
 * Reschedules itself while more events keep extending flush_at; once the window
 * has elapsed it emits the combined message(s) and deletes the batch. Stale
 * copies (from earlier resets) find the batch already deleted and no-op.
 */
final class FlushTelegramBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        if (empty(config('services.telegram.token'))) {
            return;
        }

        $payload = DB::transaction(function (): ?array {
            $batch = TelegramBatch::query()->lockForUpdate()->find($this->batchId);
            if ($batch === null) {
                return null;
            }

            if (now()->lessThan($batch->flush_at)) {
                return ['reschedule_at' => $batch->flush_at];
            }

            $events = $batch->pendingEvents()->orderBy('id')->get();
            $chatId = $batch->chat_id;
            $workspaceName = Workspace::query()->whereKey($batch->workspace_id)->value('name');

            $batch->delete();

            return ['events' => $events, 'chat_id' => $chatId, 'workspace' => $workspaceName];
        });

        if ($payload === null) {
            return;
        }

        if (isset($payload['reschedule_at'])) {
            self::dispatch($this->batchId)->delay($payload['reschedule_at']);

            return;
        }

        foreach (TelegramBatchMessage::build($payload['events'], $payload['workspace']) as $html) {
            SendTelegramMessage::dispatch($html, $payload['chat_id']);
        }
    }
}
