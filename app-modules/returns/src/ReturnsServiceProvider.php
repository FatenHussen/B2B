<?php

namespace Modules\Returns;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Domain\Events\ReturnDecided;
use Modules\Returns\Application\Listeners\ReverseOfferOnFullReturn;

class ReturnsServiceProvider extends ServiceProvider
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

        Event::listen(ReturnDecided::class, ReverseOfferOnFullReturn::class);
    }
}
