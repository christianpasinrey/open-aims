<?php

use App\Core\Providers\CoreServiceProvider;
use App\Core\Support\ModuleDiscovery;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    CoreServiceProvider::class,
    ...ModuleDiscovery::providers(__DIR__.'/../app/Modules'),
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
