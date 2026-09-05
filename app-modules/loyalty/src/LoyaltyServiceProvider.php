<?php

namespace Modules\Loyalty;

use Illuminate\Support\ServiceProvider;

class LoyaltyServiceProvider extends ServiceProvider
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
