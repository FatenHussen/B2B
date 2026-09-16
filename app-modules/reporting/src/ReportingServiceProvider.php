<?php

namespace Modules\Reporting;

use Illuminate\Support\ServiceProvider;
use Modules\Reporting\Console\GenerateDailySnapshotsCommand;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void {}

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
