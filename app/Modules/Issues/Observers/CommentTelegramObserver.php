<?php

declare(strict_types=1);

namespace App\Modules\Issues\Observers;

use App\Jobs\SendTelegramMessage;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Support\CommentTelegramFormatter;
use App\Modules\Workspaces\Support\WorkspaceTelegram;

final class CommentTelegramObserver
{
    public function created(Comment $comment): void
    {
        $issue = $comment->issue;
        if ($issue === null) {
            return;
        }
        $chatId = WorkspaceTelegram::resolveChatId((int) $issue->workspace_id);
        if ($chatId === null) {
            return;
        }

        $text = CommentTelegramFormatter::format($comment);
        if ($text === null || $text === '') {
            return;
        }

        SendTelegramMessage::dispatch($text, $chatId)->afterCommit();
    }
}
