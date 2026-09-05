<?php

declare(strict_types=1);

namespace Modules\Pricing;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\ProductPricingWriter;
use Modules\Core\Contracts\RepCommercialLimits;
use Modules\Pricing\Infrastructure\EloquentPricingEngine;
use Modules\Pricing\Infrastructure\EloquentProductPricingWriter;
use Modules\Pricing\Infrastructure\EloquentRepCommercialLimits;

class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductPricingWriter::class, EloquentProductPricingWriter::class);
        $this->app->singleton(PricingEngine::class, EloquentPricingEngine::class);
        $this->app->singleton(RepCommercialLimits::class, EloquentRepCommercialLimits::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
