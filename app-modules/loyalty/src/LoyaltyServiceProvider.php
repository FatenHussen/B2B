<?php

declare(strict_types=1);

namespace Modules\Loyalty;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\LoyaltyBalance;
use Modules\Core\Contracts\RedeemableDiscount;
use Modules\Core\Domain\Events\DeliveryCompleted;
use Modules\Loyalty\Application\Listeners\EarnOnDeliveryCompleted;
use Modules\Loyalty\Infrastructure\EloquentLoyaltyBalance;
use Modules\Loyalty\Infrastructure\EloquentRedeemableDiscount;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoyaltyBalance::class, EloquentLoyaltyBalance::class);
        $this->app->singleton(RedeemableDiscount::class, EloquentRedeemableDiscount::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->app['events']->listen(DeliveryCompleted::class, EarnOnDeliveryCompleted::class);
    }
}
