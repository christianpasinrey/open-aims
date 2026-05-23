<?php

declare(strict_types=1);

namespace App\Modules\Issues\Observers;

use App\Jobs\SendTelegramMessage;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Issues\Support\IssueActivityTelegramFormatter;
use App\Modules\Workspaces\Support\WorkspaceTelegram;

final class IssueActivityTelegramObserver
{
    public function created(IssueActivity $activity): void
    {
        $issue = $activity->issue;
        if ($issue === null) {
            return;
        }
        $chatId = WorkspaceTelegram::resolveChatId((int) $issue->workspace_id);
        if ($chatId === null) {
            return;
        }

        $text = IssueActivityTelegramFormatter::format($activity);
        if ($text === null || $text === '') {
            return;
        }

        SendTelegramMessage::dispatch($text, $chatId)->afterCommit();
    }
}
