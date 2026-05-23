<?php

declare(strict_types=1);

namespace App\Modules\Issues\Observers;

use App\Jobs\SendTelegramMessage;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Support\CommentTelegramFormatter;

final class CommentTelegramObserver
{
    public function created(Comment $comment): void
    {
        if (empty(config('services.telegram.token')) || empty(config('services.telegram.channel'))) {
            return;
        }

        $text = CommentTelegramFormatter::format($comment);
        if ($text === null || $text === '') {
            return;
        }

        SendTelegramMessage::dispatch($text)->afterCommit();
    }
}
