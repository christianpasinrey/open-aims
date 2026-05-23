<?php

declare(strict_types=1);

namespace App\Modules\Projects\Enums;

enum ProjectState: string
{
    case Backlog = 'backlog';
    case Planned = 'planned';
    case Started = 'started';
    case Paused = 'paused';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Planned => 'Planificado',
            self::Started => 'En curso',
            self::Paused => 'En pausa',
            self::Completed => 'Completado',
            self::Canceled => 'Cancelado',
        };
    }

    public static function labelFor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value)?->label() ?? $value;
    }
}
