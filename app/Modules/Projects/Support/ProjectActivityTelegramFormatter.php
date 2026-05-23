<?php

declare(strict_types=1);

namespace App\Modules\Projects\Support;

use App\Modules\Projects\Enums\ProjectState;
use App\Modules\Projects\Models\ProjectActivity;

/**
 * Turns a ProjectActivity row into an HTML message for the Telegram feed.
 *
 * Mirrors the activity kinds emitted by ProjectWriteController.
 */
final class ProjectActivityTelegramFormatter
{
    public static function format(ProjectActivity $activity): ?string
    {
        $project = $activity->project;
        if ($project === null) {
            return null;
        }

        $name = self::e($project->name);
        $url = url('/projects/'.$project->slug);
        $actor = $activity->actor?->name;
        /** @var array<string,mixed> $p */
        $p = $activity->payload ?? [];

        [$emoji, $detail] = self::describe($activity->kind, $p);
        if ($detail === null) {
            return null;
        }

        $header = "{$emoji} <b>{$name}</b>";
        $footer = '<a href="'.self::e($url).'">ver proyecto</a>';
        if ($actor !== null) {
            $footer .= ' · '.self::e($actor);
        }

        return $detail === ''
            ? $header."\n".$footer
            : $header."\n".$detail."\n".$footer;
    }

    /**
     * @param  array<string,mixed>  $p
     * @return array{0:string,1:string|null}
     */
    private static function describe(string $kind, array $p): array
    {
        return match ($kind) {
            'created' => ['🆕', 'Nuevo proyecto'],
            'state_changed' => ['🔄', 'Estado: '.self::transition(
                ProjectState::labelFor(self::str($p['from'] ?? null)),
                ProjectState::labelFor(self::str($p['to'] ?? null)),
            )],
            'priority_changed' => ['🚩', 'Prioridad: '.self::transition($p['from_label'] ?? null, $p['to_label'] ?? null)],
            'name_changed' => ['✏️', 'Nombre actualizado'],
            'description_changed' => ['📝', 'Descripción actualizada'],
            'lead_set' => ['👤', 'Responsable: <b>'.self::e((string) ($p['user_name'] ?? '—')).'</b>'],
            'lead_unset' => ['👤', 'Sin responsable'],
            'start_date_changed' => ['📅', 'Inicio: '.self::transition($p['from'] ?? null, $p['to'] ?? null)],
            'target_date_changed' => ['📅', 'Fecha objetivo: '.self::transition($p['from'] ?? null, $p['to'] ?? null)],
            'milestone_added' => ['🎯', 'Hito añadido: <b>'.self::e((string) ($p['milestone_name'] ?? '—')).'</b>'],
            'member_added' => ['➕', 'Miembro añadido: <b>'.self::e((string) ($p['user_name'] ?? '—')).'</b>'],
            'member_removed' => ['➖', 'Miembro quitado: <b>'.self::e((string) ($p['user_name'] ?? '—')).'</b>'],
            'label_added' => ['🏷️', 'Etiqueta añadida: <b>'.self::e((string) ($p['label_name'] ?? '—')).'</b>'],
            'label_removed' => ['🏷️', 'Etiqueta quitada: <b>'.self::e((string) ($p['label_name'] ?? '—')).'</b>'],
            'trashed' => ['🗑️', 'Proyecto enviado a la papelera'],
            'restored' => ['♻️', 'Proyecto restaurado'],
            default => ['•', null],
        };
    }

    private static function transition(?string $from, ?string $to): string
    {
        return '<code>'.self::e($from ?? '—').'</code> → <code>'.self::e($to ?? '—').'</code>';
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) ? $v : null;
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
