<?php

namespace Modules\Promotion;

use Illuminate\Support\ServiceProvider;

class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $routes = __DIR__.'/../routes/api.php';
        if (is_file($routes)) {
            $this->loadRoutesFrom($routes);
        }
        $migrations = __DIR__.'/../database/migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }
}
