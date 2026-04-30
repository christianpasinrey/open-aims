<?php

declare(strict_types=1);

namespace App\Core\Contracts;

interface ModuleManifest
{
    public function slug(): string;

    public function label(): string;

    public function description(): string;

    public function icon(): string;

    public function isMandatory(): bool;

    /**
     * @return array<string, int|float|bool|string|null>
     */
    public function defaultLimits(): array;

    /**
     * @return list<array{title:string, description:string, route?:string}>
     */
    public function onboardingSteps(): array;

    /**
     * @return list<string>
     */
    public function dependencies(): array;
}
