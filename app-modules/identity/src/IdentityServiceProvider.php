<?php

declare(strict_types=1);

namespace Modules\Identity;

use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ChannelUserCounter;
use Modules\Core\Contracts\OtpChannel;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Identity\Console\RegisterWarehouseDeviceCommand;
use Modules\Identity\Infrastructure\EloquentChannelUserCounter;
use Modules\Identity\Infrastructure\EloquentRepDirectory;
use Modules\Identity\Infrastructure\EloquentRepDutyLookup;
use Modules\Identity\Infrastructure\EloquentRetailerDirectory;
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
        $this->app->singleton(RetailerDirectory::class, EloquentRetailerDirectory::class);
        $this->app->singleton(RepSellingContext::class, IdentityRepSellingContext::class);
        $this->app->singleton(RepDirectory::class, EloquentRepDirectory::class);
        $this->app->singleton(RepDutyLookup::class, EloquentRepDutyLookup::class);
        $this->app->singleton(ChannelUserCounter::class, EloquentChannelUserCounter::class);
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

        // Append after auth on every app route so pending_review is enforced without
        // each operational module naming the middleware (BE-I06).
        $this->app->booted(function (): void {
            foreach ($this->app['router']->getRoutes() as $route) {
                if (! $route instanceof Route) {
                    continue;
                }

                if (! str_starts_with($route->uri(), 'api/v1/app/')) {
                    continue;
                }

                $route->middleware('retailer.profile');
            }
        });
    }
}
