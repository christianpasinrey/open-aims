<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stub Vite so Inertia root templates render without a built manifest.
        $this->app->singleton(Vite::class, function () {
            return new class extends Vite
            {
                public function __invoke($entrypoints, $buildDirectory = null): HtmlString
                {
                    return new HtmlString('');
                }

                public function reactRefresh(): ?HtmlString
                {
                    return null;
                }
            };
        });

        // Map module model class names → flat factories under Database\Factories.
        Factory::guessFactoryNamesUsing(static function (string $modelName): string {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
