<?php

declare(strict_types=1);

namespace Modules\Tenancy;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Tenancy\Domain\ChannelStateMachine;
use Modules\Tenancy\Infrastructure\EloquentChannelDirectory;
use Modules\Tenancy\Infrastructure\EloquentWarehouseDirectory;
use Modules\Tenancy\Infrastructure\StubChannelLimits;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');
        $this->app->singleton(ChannelDirectory::class, EloquentChannelDirectory::class);
        $this->app->singleton(WarehouseDirectory::class, EloquentWarehouseDirectory::class);
        $this->app->singleton(ChannelLimits::class, StubChannelLimits::class);
        $this->app->singleton(ChannelStateMachine::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
