<?php

declare(strict_types=1);

namespace App\Modules\Issues\Support;

use App\Modules\Issues\Models\IssueActivity;

/**
 * Turns an IssueActivity row into an HTML message for the Telegram feed.
 *
 * Mirrors the activity kinds emitted by IssueWriteController::recordIssueChanges.
 * Returns null for kinds we don't want to surface in the channel.
 */
final class IssueActivityTelegramFormatter
{
    public static function format(IssueActivity $activity): ?string
    {
        $issue = $activity->issue;
        if ($issue === null) {
            return null;
        }

        $identifier = $issue->identifier();
        $title = self::e($issue->title);
        $url = url('/issues/'.$identifier);
        $actor = $activity->actor?->name;
        /** @var array<string,mixed> $p */
        $p = $activity->payload ?? [];

        [$emoji, $detail] = self::describe($activity->kind, $p);
        if ($detail === null) {
            return null;
        }

        $header = "{$emoji} <b>".self::e($identifier).'</b> — '.$title;
        $footer = '<a href="'.self::e($url).'">ver incidencia</a>';
        if ($actor !== null) {
            $footer .= ' · '.self::e($actor);
        }

        return $detail === ''
            ? $header."\n".$footer
            : $header."\n".$detail."\n".$footer;
    }

    /**
     * @param  array<string,mixed>  $p
     * @return array{0:string,1:string|null} [emoji, detail] — detail null skips the message
     */
    private static function describe(string $kind, array $p): array
    {
        return match ($kind) {
            'created' => ['🆕', 'Nueva incidencia'],
            'status_changed' => ['🔄', 'Estado: '.self::transition(
                self::arr($p, 'from')['name'] ?? null,
                self::arr($p, 'to')['name'] ?? null,
            )],
            'assigned' => ['👤', 'Asignada a <b>'.self::e((string) ($p['user_name'] ?? '—')).'</b>'],
            'unassigned' => ['👤', 'Sin asignar'],
            'priority_changed' => ['🚩', 'Prioridad: '.self::transition($p['from_label'] ?? null, $p['to_label'] ?? null)],
            'title_changed' => ['✏️', 'Título actualizado'],
            'description_changed' => ['📝', 'Descripción actualizada'],
            'project_set' => ['📁', 'Proyecto: <b>'.self::e((string) ($p['project_name'] ?? '—')).'</b>'],
            'project_unset' => ['📁', 'Quitada del proyecto'],
            'cycle_set' => ['🔁', 'Ciclo: <b>'.self::e((string) ($p['cycle_name'] ?? '—')).'</b>'],
            'cycle_unset' => ['🔁', 'Quitada del ciclo'],
            'due_date_changed' => ['📅', 'Fecha límite: '.self::transition($p['from'] ?? null, $p['to'] ?? null)],
            'estimate_changed' => ['⏱️', 'Estimación: '.self::transition(
                self::num($p['from'] ?? null),
                self::num($p['to'] ?? null),
            )],
            'label_added' => ['🏷️', 'Etiqueta añadida: <b>'.self::e((string) ($p['label_name'] ?? '—')).'</b>'],
            'label_removed' => ['🏷️', 'Etiqueta quitada: <b>'.self::e((string) ($p['label_name'] ?? '—')).'</b>'],
            'archived' => ['🗄️', 'Archivada'],
            'unarchived' => ['🗄️', 'Desarchivada'],
            default => ['•', null],
        };
    }

    private static function transition(?string $from, ?string $to): string
    {
        return '<code>'.self::e($from ?? '—').'</code> → <code>'.self::e($to ?? '—').'</code>';
    }

    private static function num(mixed $v): ?string
    {
        return $v === null ? null : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string,mixed>  $p
     * @return array<string,mixed>
     */
    private static function arr(array $p, string $key): array
    {
        $v = $p[$key] ?? null;

        return is_array($v) ? $v : [];
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
