<?php

namespace Modules\PlatformBilling;

use Illuminate\Support\ServiceProvider;

class PlatformBillingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $migrations = __DIR__.'/../database/migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }
}
