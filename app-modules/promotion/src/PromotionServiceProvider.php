<?php

declare(strict_types=1);

namespace Modules\Promotion;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\OfferApplicator;
use Modules\Core\Contracts\OfferConsumption;
use Modules\Core\Contracts\OfferFeed;
use Modules\Promotion\Infrastructure\EloquentOfferApplicator;
use Modules\Promotion\Infrastructure\EloquentOfferConsumption;
use Modules\Promotion\Infrastructure\EloquentOfferFeed;

class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OfferFeed::class, EloquentOfferFeed::class);
        $this->app->singleton(OfferApplicator::class, EloquentOfferApplicator::class);
        $this->app->singleton(OfferConsumption::class, EloquentOfferConsumption::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
