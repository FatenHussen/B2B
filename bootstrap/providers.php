<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

// TelescopeServiceProvider is deliberately absent: laravel/telescope is a dev
// dependency, so listing it here breaks every `composer install --no-dev` deploy.
// AppServiceProvider registers it only where the package is installed.
return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
];
