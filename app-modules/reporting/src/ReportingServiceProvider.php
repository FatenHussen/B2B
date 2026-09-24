<?php

namespace Modules\Reporting;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ChannelJobRegistry;
use Modules\Reporting\Console\GenerateDailySnapshotsCommand;
use Modules\Reporting\Infrastructure\EloquentChannelJobRegistry;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelJobRegistry::class, EloquentChannelJobRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDailySnapshotsCommand::class,
            ]);
        }
    }
}
