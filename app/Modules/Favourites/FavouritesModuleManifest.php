<?php

declare(strict_types=1);

namespace App\Modules\Favourites;

use App\Core\Contracts\ModuleManifest;

final class FavouritesModuleManifest implements ModuleManifest
{
    public function slug(): string
    {
        return 'favourites';
    }

    public function label(): string
    {
        return 'Favourites';
    }

    public function description(): string
    {
        return 'Per-user starred items (issues, projects, cycles, views, pages) surfaced in the sidebar.';
    }

    public function icon(): string
    {
        return 'star';
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function defaultLimits(): array
    {
        return [
            'max_favourites_per_workspace' => 200,
        ];
    }

    public function onboardingSteps(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return ['workspaces'];
    }
}
