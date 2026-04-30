<?php

declare(strict_types=1);

namespace App\Modules\Favourites;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;

final class FavouritesServiceProvider extends ModuleServiceProvider
{
    public function slug(): string
    {
        return 'favourites';
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ModuleRegistry::class)->register(new FavouritesModuleManifest);
    }
}
