<?php

declare(strict_types=1);

namespace App\Modules\Issues\Observers;

use App\Jobs\SendTelegramMessage;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Issues\Support\IssueActivityTelegramFormatter;

final class IssueActivityTelegramObserver
{
    public function created(IssueActivity $activity): void
    {
        if (empty(config('services.telegram.token')) || empty(config('services.telegram.channel'))) {
            return;
        }

        $text = IssueActivityTelegramFormatter::format($activity);
        if ($text === null || $text === '') {
            return;
        }

        SendTelegramMessage::dispatch($text)->afterCommit();
    }
}
