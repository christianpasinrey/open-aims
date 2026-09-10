<?php

declare(strict_types=1);

namespace App\Modules\Graphs;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;

final class GraphsServiceProvider extends ModuleServiceProvider
{
    public function slug(): string
    {
        return 'graphs';
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ModuleRegistry::class)->register(new GraphsModuleManifest);
    }
}
