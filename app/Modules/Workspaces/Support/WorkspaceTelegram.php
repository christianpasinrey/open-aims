<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Support;

use App\Modules\Workspaces\Models\Workspace;

final class WorkspaceTelegram
{
    public static function resolveChatId(int $workspaceId): ?string
    {
        $token = config('services.telegram.token');
        if (empty($token)) {
            return null;
        }

        $workspace = Workspace::query()->find($workspaceId);
        if ($workspace === null) {
            return null;
        }

        $settings = is_array($workspace->settings) ? $workspace->settings : [];
        $telegram = is_array($settings['telegram'] ?? null) ? $settings['telegram'] : [];

        if (($telegram['enabled'] ?? false) !== true) {
            return null;
        }

        $chatId = $telegram['chat_id'] ?? null;
        if (is_string($chatId) && $chatId !== '') {
            return $chatId;
        }

        $global = config('services.telegram.channel');

        return ! empty($global) ? (string) $global : null;
    }
}
