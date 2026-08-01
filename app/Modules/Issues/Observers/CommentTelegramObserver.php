<?php

declare(strict_types=1);

namespace App\Modules\Issues\Observers;

use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Support\CommentTelegramFormatter;
use App\Modules\Issues\Support\IssueMentionResolver;
use App\Modules\Workspaces\Support\WorkspaceTelegram;
use App\Support\Telegram\TelegramBatcher;

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

        app(TelegramBatcher::class)->enqueue(
            (int) $issue->workspace_id,
            $chatId,
            $text,
            IssueMentionResolver::forComment($comment),
        );
    }
}
