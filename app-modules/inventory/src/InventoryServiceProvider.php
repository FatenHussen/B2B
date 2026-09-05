<?php

declare(strict_types=1);

namespace Modules\Inventory;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\StockLedger;
use Modules\Inventory\Infrastructure\EloquentStockLedger;
use Modules\Inventory\Infrastructure\StockAvailabilityClassifier;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StockLedger::class, EloquentStockLedger::class);
        $this->app->singleton(AvailabilityClassifier::class, StockAvailabilityClassifier::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
