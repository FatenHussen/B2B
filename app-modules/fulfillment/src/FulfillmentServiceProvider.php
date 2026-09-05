<?php

declare(strict_types=1);

namespace Modules\Fulfillment;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\CreatesPickingList;
use Modules\Core\Contracts\HandoverGuard;
use Modules\Core\Domain\Events\SubOrderConfirmed;
use Modules\Fulfillment\Application\Listeners\CreatePickingListOnConfirm;
use Modules\Fulfillment\Infrastructure\EloquentCreatesPickingList;
use Modules\Fulfillment\Infrastructure\EloquentHandoverGuard;

class FulfillmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreatesPickingList::class, EloquentCreatesPickingList::class);
        $this->app->singleton(HandoverGuard::class, EloquentHandoverGuard::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->app['events']->listen(SubOrderConfirmed::class, CreatePickingListOnConfirm::class);
    }
}
