<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Core\Contracts\ModuleManifest;
use App\Core\Contracts\ProvidesMcpTools;
use App\Modules\Views\Mcp\Tools\ViewsList;

final class ViewsModuleManifest implements ModuleManifest, ProvidesMcpTools
{
    public function slug(): string
    {
        return 'views';
    }

    public function label(): string
    {
        return 'Views';
    }

    public function description(): string
    {
        return 'Saved searches over the issues list (filters + grouping + sort).';
    }

    public function icon(): string
    {
        return 'layers';
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function defaultLimits(): array
    {
        return [
            'max_views_per_user' => 200,
        ];
    }

    public function onboardingSteps(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return ['workspaces', 'issues'];
    }

    public function mcpTools(): array
    {
        return [
            ViewsList::class,
        ];
    }
}
