<?php

declare(strict_types=1);

namespace Modules\Sync;

use Illuminate\Support\ServiceProvider;
use Modules\Sync\Application\Actions\PushSyncOperations;

class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushSyncOperations::class, function ($app): PushSyncOperations {
            return new PushSyncOperations($app->tagged('sync.operation'));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
