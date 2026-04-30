<?php

declare(strict_types=1);

namespace App\Modules\Views\Enums;

enum ViewScope: string
{
    case Personal = 'personal';
    case Team = 'team';
    case Workspace = 'workspace';
}
