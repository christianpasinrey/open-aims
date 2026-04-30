<?php

declare(strict_types=1);

namespace App\Modules\Issues;

use App\Core\Contracts\ModuleManifest;

final class IssuesModuleManifest implements ModuleManifest
{
    public function slug(): string
    {
        return 'issues';
    }

    public function label(): string
    {
        return 'Issues';
    }

    public function description(): string
    {
        return 'The unit of work — bug reports, features, tasks. Includes comments, labels, sub-issues and reactions.';
    }

    public function icon(): string
    {
        return 'circle-dot';
    }

    public function isMandatory(): bool
    {
        return true;
    }

    public function defaultLimits(): array
    {
        return [
            'max_open_issues_per_team' => 10000,
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
