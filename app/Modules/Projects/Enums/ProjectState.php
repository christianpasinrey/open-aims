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
}
