<?php

declare(strict_types=1);

namespace App\Core\Registries;

use App\Core\Contracts\ModuleManifest;

final class ModuleRegistry
{
    /** @var array<string, ModuleManifest> */
    private array $manifests = [];

    public function register(ModuleManifest $manifest): void
    {
        $this->manifests[$manifest->slug()] = $manifest;
    }

    public function get(string $slug): ?ModuleManifest
    {
        return $this->manifests[$slug] ?? null;
    }

    /** @return array<string, ModuleManifest> */
    public function all(): array
    {
        return $this->manifests;
    }

    /** @return list<ModuleManifest> */
    public function mandatory(): array
    {
        return array_values(array_filter(
            $this->manifests,
            static fn (ModuleManifest $m): bool => $m->isMandatory(),
        ));
    }

    /** @return list<ModuleManifest> */
    public function optional(): array
    {
        return array_values(array_filter(
            $this->manifests,
            static fn (ModuleManifest $m): bool => ! $m->isMandatory(),
        ));
    }
}
