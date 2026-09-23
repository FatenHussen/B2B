<?php

declare(strict_types=1);

namespace Modules\Catalog;

use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Infrastructure\ActiveProductAvailabilityClassifier;
use Modules\Catalog\Infrastructure\EloquentCatalogProductLookup;
use Modules\Catalog\Infrastructure\EloquentCatalogSyncSource;
use Modules\Catalog\Infrastructure\EmptyOfferFeed;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\CatalogSyncSource;
use Modules\Core\Contracts\OfferFeed;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CatalogProductLookup::class, EloquentCatalogProductLookup::class);
        $this->app->singleton(CatalogSyncSource::class, EloquentCatalogSyncSource::class);
        if (! $this->app->bound(AvailabilityClassifier::class)) {
            $this->app->singleton(AvailabilityClassifier::class, ActiveProductAvailabilityClassifier::class);
        }
        if (! $this->app->bound(OfferFeed::class)) {
            $this->app->singleton(OfferFeed::class, EmptyOfferFeed::class);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
