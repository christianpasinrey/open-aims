<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts a single HTML message to the configured Telegram channel.
 *
 * No-op (and logs nothing) when the bot token or channel id are missing, so
 * environments without Telegram configured don't fail or spam the queue.
 */
final class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public string $html,
        public ?string $chatId = null,
    ) {}

    public function handle(): void
    {
        $token = config('services.telegram.token');
        $channel = $this->chatId ?? config('services.telegram.channel');

        if (empty($token) || empty($channel)) {
            return;
        }

        $response = Http::asJson()
            ->timeout(15)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $channel,
                'text' => $this->html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if ($response->failed()) {
            Log::warning('Telegram sendMessage failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            // Let the queue retry transient failures.
            $this->release($this->backoff);
        }
    }
}
