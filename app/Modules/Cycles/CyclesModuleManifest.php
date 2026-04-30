<?php

declare(strict_types=1);

namespace App\Modules\Cycles;

use App\Core\Contracts\ModuleManifest;
use App\Core\Contracts\ProvidesMcpTools;
use App\Modules\Cycles\Mcp\Tools\CyclesCreate;
use App\Modules\Cycles\Mcp\Tools\CyclesGet;
use App\Modules\Cycles\Mcp\Tools\CyclesList;

final class CyclesModuleManifest implements ModuleManifest, ProvidesMcpTools
{
    public function slug(): string
    {
        return 'cycles';
    }

    public function label(): string
    {
        return 'Cycles';
    }

    public function description(): string
    {
        return 'Time-boxed iterations (sprints) per team. Optional but recommended for delivery cadence.';
    }

    public function icon(): string
    {
        return 'rotate-cw';
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function defaultLimits(): array
    {
        return [
            'max_active_cycles_per_team' => 4,
        ];
    }

    public function onboardingSteps(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return ['workspaces', 'teams', 'issues'];
    }

    public function mcpTools(): array
    {
        return [
            CyclesList::class,
            CyclesGet::class,
            CyclesCreate::class,
        ];
    }
}
