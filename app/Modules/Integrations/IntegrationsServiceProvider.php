<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;

final class IntegrationsServiceProvider extends ModuleServiceProvider
{
    public function slug(): string
    {
        return 'integrations';
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ModuleRegistry::class)->register(new IntegrationsModuleManifest);

        // Sub-integrations live in their own directories; load their
        // migrations explicitly since ModuleServiceProvider only scans
        // the top-level Integrations/Database/Migrations folder.
        $githubMigrations = __DIR__.'/Github/Database/Migrations';
        if (is_dir($githubMigrations)) {
            $this->loadMigrationsFrom($githubMigrations);
        }
    }
}
