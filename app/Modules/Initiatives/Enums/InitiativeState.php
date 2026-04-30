<?php

declare(strict_types=1);

namespace App\Modules\Initiatives\Enums;

enum InitiativeState: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
