<?php

declare(strict_types=1);

namespace App\Modules\Initiatives;

use App\Core\Contracts\ModuleManifest;

final class InitiativesModuleManifest implements ModuleManifest
{
    public function slug(): string
    {
        return 'initiatives';
    }

    public function label(): string
    {
        return 'Initiatives';
    }

    public function description(): string
    {
        return 'Workspace-level groupings of projects with aggregated progress.';
    }

    public function icon(): string
    {
        return 'target';
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function defaultLimits(): array
    {
        return [
            'max_initiatives_per_workspace' => 100,
        ];
    }

    public function onboardingSteps(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return ['workspaces', 'projects'];
    }
}
