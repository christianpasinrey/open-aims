<?php

declare(strict_types=1);

namespace App\Modules\Issues\Support;

use App\Models\User;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\IssueActivity;

/**
 * Resolves the Telegram @mention for an event, or null when nobody should be pinged.
 *
 * - assigned activity → the assignee (unless they assigned themselves)
 * - comment reply     → the parent comment author (unless replying to themselves)
 *
 * Users without a telegram_username are never mentioned.
 */
final class IssueMentionResolver
{
    public static function forActivity(IssueActivity $activity): ?string
    {
        if ($activity->kind !== 'assigned') {
            return null;
        }

        $payload = is_array($activity->payload) ? $activity->payload : [];
        $assigneeId = $payload['user_id'] ?? null;
        if ($assigneeId === null || (int) $assigneeId === (int) $activity->actor_user_id) {
            return null;
        }

        return self::handleFor((int) $assigneeId);
    }

    public static function forComment(Comment $comment): ?string
    {
        if ($comment->parent_comment_id === null) {
            return null;
        }

        $parent = $comment->parent;
        if ($parent === null || (int) $parent->user_id === (int) $comment->user_id) {
            return null;
        }

        return self::handleFor((int) $parent->user_id);
    }

    private static function handleFor(int $userId): ?string
    {
        $handle = User::query()->whereKey($userId)->value('telegram_username');
        if (! is_string($handle) || trim($handle) === '') {
            return null;
        }

        return '@'.ltrim(trim($handle), '@');
    }
}
