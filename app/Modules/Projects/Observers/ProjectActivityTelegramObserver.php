<?php

declare(strict_types=1);

namespace App\Modules\Projects\Observers;

use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Support\ProjectActivityTelegramFormatter;
use App\Modules\Workspaces\Support\WorkspaceTelegram;
use App\Support\Telegram\TelegramBatcher;

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

        app(TelegramBatcher::class)->enqueue((int) $project->workspace_id, $chatId, $text, null);
    }
}
