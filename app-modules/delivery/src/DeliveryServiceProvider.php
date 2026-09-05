<?php

declare(strict_types=1);

namespace Modules\Delivery;

use Illuminate\Support\ServiceProvider;

class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
