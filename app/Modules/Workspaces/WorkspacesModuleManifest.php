<?php

declare(strict_types=1);

namespace App\Modules\Workspaces;

use App\Core\Contracts\ModuleManifest;

final class WorkspacesModuleManifest implements ModuleManifest
{
    public function slug(): string
    {
        return 'workspaces';
    }

    public function label(): string
    {
        return 'Workspaces';
    }

    public function description(): string
    {
        return 'Organizations, members, invitations and access control.';
    }

    public function icon(): string
    {
        return 'building-2';
    }

    public function isMandatory(): bool
    {
        return true;
    }

    public function defaultLimits(): array
    {
        return [
            'max_workspaces_per_user' => 10,
            'max_members_per_workspace' => 250,
        ];
    }

    public function onboardingSteps(): array
    {
        return [
            ['title' => 'Create your workspace', 'description' => 'Name your repo-lab space.'],
            ['title' => 'Invite teammates', 'description' => 'Bring the team in by email.'],
        ];
    }

    public function dependencies(): array
    {
        return [];
    }
}
