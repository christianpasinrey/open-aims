<?php

declare(strict_types=1);

namespace App\Modules\Teams\Enums;

enum WorkflowStateType: string
{
    case Backlog = 'backlog';
    case Unstarted = 'unstarted';
    case Started = 'started';
    case Completed = 'completed';
    case Canceled = 'canceled';
    case Triage = 'triage';
}
