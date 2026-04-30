<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;

final class ViewsServiceProvider extends ModuleServiceProvider
{
    public function slug(): string
    {
        return 'views';
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ModuleRegistry::class)->register(new ViewsModuleManifest);
    }
}
