<?php

declare(strict_types=1);

namespace App\Modules\Projects;

use App\Core\Contracts\ModuleManifest;

final class ProjectsModuleManifest implements ModuleManifest
{
    public function slug(): string
    {
        return 'projects';
    }

    public function label(): string
    {
        return 'Projects';
    }

    public function description(): string
    {
        return 'Cross-team initiatives with milestones, leads and target dates.';
    }

    public function icon(): string
    {
        return 'folder-kanban';
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function defaultLimits(): array
    {
        return [
            'max_active_projects_per_workspace' => 200,
        ];
    }

    public function onboardingSteps(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return ['workspaces', 'teams'];
    }
}
