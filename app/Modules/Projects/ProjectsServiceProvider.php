<?php

declare(strict_types=1);

namespace App\Modules\Projects;

use App\Core\Registries\ModuleRegistry;
use App\Core\Support\ModuleServiceProvider;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectActivity;
use App\Modules\Projects\Observers\ProjectActivityTelegramObserver;
use App\Modules\Projects\Observers\ProjectObserver;

final class ProjectsServiceProvider extends ModuleServiceProvider
{
    public function slug(): string
    {
        return 'projects';
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ModuleRegistry::class)->register(new ProjectsModuleManifest);

        Project::observe(ProjectObserver::class);
        ProjectActivity::observe(ProjectActivityTelegramObserver::class);
    }
}
