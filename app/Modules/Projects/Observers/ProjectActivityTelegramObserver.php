<?php

declare(strict_types=1);

namespace App\Modules\Projects\Observers;

use App\Jobs\SendTelegramMessage;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Support\ProjectActivityTelegramFormatter;
use App\Modules\Workspaces\Support\WorkspaceTelegram;

final class ProjectActivityTelegramObserver
{
    public function created(ProjectActivity $activity): void
    {
        $project = $activity->project;
        if ($project === null) {
            return;
        }
        $chatId = WorkspaceTelegram::resolveChatId((int) $project->workspace_id);
        if ($chatId === null) {
            return;
        }

        $text = ProjectActivityTelegramFormatter::format($activity);
        if ($text === null || $text === '') {
            return;
        }

        SendTelegramMessage::dispatch($text, $chatId)->afterCommit();
    }
}
