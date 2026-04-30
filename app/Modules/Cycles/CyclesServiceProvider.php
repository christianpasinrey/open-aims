<?php

declare(strict_types=1);

namespace App\Modules\Cycles;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;

final class CyclesServiceProvider extends ModuleServiceProvider
{
    public function slug(): string
    {
        return 'cycles';
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ModuleRegistry::class)->register(new CyclesModuleManifest);
    }
}
