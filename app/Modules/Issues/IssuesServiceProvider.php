<?php

declare(strict_types=1);

namespace App\Modules\Issues;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Observers\IssueObserver;

final class IssuesServiceProvider extends ModuleServiceProvider
{
    public function slug(): string
    {
        return 'issues';
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ModuleRegistry::class)->register(new IssuesModuleManifest);

        Issue::observe(IssueObserver::class);
    }
}
