<?php

declare(strict_types=1);

namespace App\Modules\Issues\Support;

use App\Modules\Issues\Models\Comment;
use Illuminate\Support\Str;

/**
 * Turns a Comment into an HTML message for the Telegram feed. Comments are
 * not logged as IssueActivity rows (to avoid duplicating the UI timeline),
 * so they are surfaced straight from the Comment model.
 */
final class CommentTelegramFormatter
{
    public static function format(Comment $comment): ?string
    {
        $issue = $comment->issue;
        if ($issue === null) {
            return null;
        }

        $identifier = $issue->identifier();
        $title = self::e($issue->title);
        $url = url('/issues/'.$identifier);
        $author = $comment->user?->name;

        $excerpt = self::e(Str::limit(trim(strip_tags($comment->body)), 280));
        $isReply = $comment->parent_comment_id !== null;
        $verb = $isReply ? 'Respuesta' : 'Comentario';

        $header = '💬 <b>'.self::e($identifier).'</b> — '.$title;
        $footer = '<a href="'.self::e($url).'">ver incidencia</a>';
        if ($author !== null) {
            $footer .= ' · '.self::e($author);
        }

        return $header."\n<i>{$verb}:</i> ".$excerpt."\n".$footer;
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
