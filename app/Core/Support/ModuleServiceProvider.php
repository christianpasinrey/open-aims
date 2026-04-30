<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use RuntimeException;

abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract public function slug(): string;

    public function register(): void
    {
        $configPath = $this->modulePath('config.php');
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, "modules.{$this->slug()}");
        }
    }

    public function boot(): void
    {
        $routesPath = $this->modulePath('routes.php');
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }

        $migrationsPath = $this->modulePath('Database/Migrations');
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }

        $langPath = $this->modulePath('lang');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->slug());
        }
    }

    protected function modulePath(string $relative = ''): string
    {
        $reflection = new ReflectionClass(static::class);
        $filePath = $reflection->getFileName();

        if ($filePath === false) {
            throw new RuntimeException('Unable to resolve module path for '.static::class);
        }

        return rtrim(dirname($filePath), DIRECTORY_SEPARATOR)
            .($relative === '' ? '' : DIRECTORY_SEPARATOR.$relative);
    }
}
