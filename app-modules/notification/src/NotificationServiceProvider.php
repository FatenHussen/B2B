<?php

declare(strict_types=1);

namespace Modules\Notification;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\AppInbox;
use Modules\Core\Domain\Events\DeliveryCompleted;
use Modules\Core\Domain\Events\HandoverConfirmedByRep;
use Modules\Core\Domain\Events\SubOrderAssigned;
use Modules\Core\Domain\Events\SubOrderConfirmed;
use Modules\Notification\Application\Listeners\NotifyOnDeliveryCompleted;
use Modules\Notification\Application\Listeners\NotifyOnHandoverConfirmed;
use Modules\Notification\Application\Listeners\NotifyOnSubOrderAssigned;
use Modules\Notification\Application\Listeners\NotifyOnSubOrderConfirmed;
use Modules\Notification\Infrastructure\EloquentAppInbox;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppInbox::class, EloquentAppInbox::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->app['events']->listen(SubOrderAssigned::class, NotifyOnSubOrderAssigned::class);
        $this->app['events']->listen(SubOrderConfirmed::class, NotifyOnSubOrderConfirmed::class);
        $this->app['events']->listen(HandoverConfirmedByRep::class, NotifyOnHandoverConfirmed::class);
        $this->app['events']->listen(DeliveryCompleted::class, NotifyOnDeliveryCompleted::class);
    }
}
