<?php

declare(strict_types=1);

namespace Modules\Identity;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\OtpChannel;
use Modules\Identity\Console\RegisterWarehouseDeviceCommand;
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
