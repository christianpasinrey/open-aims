<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Core\Contracts\ModuleManifest;

final class IntegrationsModuleManifest implements ModuleManifest
{
    public function slug(): string
    {
        return 'integrations';
    }

    public function label(): string
    {
        return 'Integrations';
    }

    public function description(): string
    {
        return 'One-shot importers and bridges with external systems (repo, GitHub, etc).';
    }

    public function icon(): string
    {
        return 'plug-zap';
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function defaultLimits(): array
    {
        return [];
    }

    public function onboardingSteps(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return ['workspaces', 'teams', 'issues'];
    }
}
