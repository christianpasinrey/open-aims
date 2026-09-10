<?php

declare(strict_types=1);

namespace App\Modules\Graphs;

use App\Core\Contracts\ModuleManifest;
use App\Core\Contracts\ProvidesMcpTools;

final class GraphsModuleManifest implements ModuleManifest, ProvidesMcpTools
{
    public function slug(): string
    {
        return 'graphs';
    }

    public function label(): string
    {
        return 'Graphs';
    }

    public function description(): string
    {
        return 'Graphs of code references (files, classes, functions, resources) attached to issues, milestones and projects, merged into a workspace-wide map.';
    }

    public function icon(): string
    {
        return 'waypoints';
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function defaultLimits(): array
    {
        return [
            'max_graphs_per_owner' => 50,
        ];
    }

    public function onboardingSteps(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return ['workspaces', 'issues', 'projects'];
    }

    public function mcpTools(): array
    {
        return [];
    }
}
