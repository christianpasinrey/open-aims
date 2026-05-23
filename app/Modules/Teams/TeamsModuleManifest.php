<?php

declare(strict_types=1);

namespace App\Modules\Teams;

use App\Core\Contracts\ModuleManifest;
use App\Core\Contracts\ProvidesMcpTools;
use App\Modules\Teams\Mcp\Tools\TeamsCreate;
use App\Modules\Teams\Mcp\Tools\TeamsList;

final class TeamsModuleManifest implements ModuleManifest, ProvidesMcpTools
{
    public function slug(): string
    {
        return 'teams';
    }

    public function label(): string
    {
        return 'Teams';
    }

    public function description(): string
    {
        return 'Sub-organizations within a workspace, each with its own workflows, labels and issue keys.';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function isMandatory(): bool
    {
        return true;
    }

    public function defaultLimits(): array
    {
        return [
            'max_teams_per_workspace' => 50,
            'max_members_per_team' => 100,
        ];
    }

    public function onboardingSteps(): array
    {
        return [
            ['title' => 'Create your first team', 'description' => 'Pick a name and a 2-5 letter key (e.g. ENG).'],
        ];
    }

    public function dependencies(): array
    {
        return ['workspaces'];
    }

    public function mcpTools(): array
    {
        return [
            TeamsCreate::class,
            TeamsList::class,
        ];
    }
}
