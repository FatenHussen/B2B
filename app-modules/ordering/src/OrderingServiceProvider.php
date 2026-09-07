<?php

declare(strict_types=1);

namespace Modules\Ordering;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\CreatesPickingList;
use Modules\Core\Contracts\HandoverGuard;
use Modules\Core\Contracts\OpenOrderCounter;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Ordering\Domain\SubOrderStateMachine;
use Modules\Ordering\Infrastructure\EloquentOpenOrderCounter;
use Modules\Ordering\Infrastructure\EloquentSubOrderLifecycle;

class OrderingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SubOrderStateMachine::class);
        $this->app->singleton(SubOrderLifecycle::class, EloquentSubOrderLifecycle::class);
        $this->app->singleton(OpenOrderCounter::class, EloquentOpenOrderCounter::class);

        if (! $this->app->bound(HandoverGuard::class)) {
            $this->app->singleton(HandoverGuard::class, fn () => new class implements HandoverGuard
            {
                public function isConfirmedForSubOrder(int $subOrderId): bool
                {
                    return false;
                }
            });
        }

        if (! $this->app->bound(CreatesPickingList::class)) {
            $this->app->singleton(CreatesPickingList::class, fn () => new class implements CreatesPickingList
            {
                public function create(array $payload): int
                {
                    return 0;
                }

                public function idForSubOrder(int $subOrderId): ?int
                {
                    return null;
                }
            });
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
