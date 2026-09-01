<?php

declare(strict_types=1);

namespace Modules\Identity;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\OtpChannel;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Identity\Console\RegisterWarehouseDeviceCommand;
use Modules\Identity\Infrastructure\EloquentRepDirectory;
use Modules\Identity\Infrastructure\IdentityRepSellingContext;
use Modules\Identity\Infrastructure\IdentityRetailerShoppingContext;
use Modules\Identity\Infrastructure\Otp\FakeOtpChannel;
use Modules\Identity\Infrastructure\Otp\LogOtpChannel;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('testing')) {
            $this->app->singleton(OtpChannel::class, FakeOtpChannel::class);
        } else {
            $this->app->bind(OtpChannel::class, LogOtpChannel::class);
        }

        $this->app->singleton(RetailerShoppingContext::class, IdentityRetailerShoppingContext::class);
        $this->app->singleton(RepSellingContext::class, IdentityRepSellingContext::class);
        $this->app->singleton(RepDirectory::class, EloquentRepDirectory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterWarehouseDeviceCommand::class,
            ]);
        }
    }
}
