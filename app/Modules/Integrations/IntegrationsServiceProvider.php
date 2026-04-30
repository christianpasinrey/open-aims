<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;
use App\Modules\Integrations\repo\Console\ImportFromrepoCommand;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportFromrepoCommand::class,
            ]);
        }
    }
}
