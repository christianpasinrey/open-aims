<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Http\Middleware\EnsureModuleActive;
use App\Core\Http\Middleware\ResolveWorkspace;
use App\Core\Registries\ModuleRegistry;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('workspace', ResolveWorkspace::class);
        $router->aliasMiddleware('module', EnsureModuleActive::class);
    }
}
