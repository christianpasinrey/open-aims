<?php

declare(strict_types=1);

namespace App\Modules\Issues\Enums;

enum IssuePriority: int
{
    case None = 0;
    case Urgent = 1;
    case High = 2;
    case Medium = 3;
    case Low = 4;

    public function label(): string
    {
        return match ($this) {
            self::None => 'No priority',
            self::Urgent => 'Urgent',
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }
}
