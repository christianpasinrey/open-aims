<?php

declare(strict_types=1);

namespace App\Modules\Teams;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;

final class TeamsServiceProvider extends ModuleServiceProvider
{
    public function slug(): string
    {
        return 'teams';
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ModuleRegistry::class)->register(new TeamsModuleManifest);
    }
}
