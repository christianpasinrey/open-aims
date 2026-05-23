<?php

declare(strict_types=1);

namespace App\Modules\Projects\Observers;

use App\Jobs\SendTelegramMessage;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Support\ProjectActivityTelegramFormatter;

final class ProjectActivityTelegramObserver
{
    public function created(ProjectActivity $activity): void
    {
        if (empty(config('services.telegram.token')) || empty(config('services.telegram.channel'))) {
            return;
        }

        $text = ProjectActivityTelegramFormatter::format($activity);
        if ($text === null || $text === '') {
            return;
        }

        SendTelegramMessage::dispatch($text)->afterCommit();
    }
}
