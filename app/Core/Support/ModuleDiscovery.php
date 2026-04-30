<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Support\ServiceProvider;

final class ModuleDiscovery
{
    /**
     * @return list<class-string<ServiceProvider>>
     */
    public static function providers(string $modulesPath): array
    {
        if (! is_dir($modulesPath)) {
            return [];
        }

        $providers = [];

        foreach (scandir($modulesPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $moduleDir = $modulesPath.DIRECTORY_SEPARATOR.$entry;
            if (! is_dir($moduleDir)) {
                continue;
            }

            $providerFile = $moduleDir.DIRECTORY_SEPARATOR.$entry.'ServiceProvider.php';
            if (! is_file($providerFile)) {
                continue;
            }

            /** @var class-string $class */
            $class = "App\\Modules\\{$entry}\\{$entry}ServiceProvider";
            if (class_exists($class) && is_subclass_of($class, ServiceProvider::class)) {
                $providers[] = $class;
            }
        }

        sort($providers);

        /** @var list<class-string<ServiceProvider>> $providers */
        return $providers;
    }
}
