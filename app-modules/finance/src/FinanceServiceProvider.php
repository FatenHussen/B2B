<?php

declare(strict_types=1);

namespace Modules\Finance;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ChannelFinanceMetrics;
use Modules\Core\Contracts\CreditGuard;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\ReceiptNumberReserver;
use Modules\Core\Contracts\RepCollectedToday;
use Modules\Finance\Infrastructure\EloquentChannelFinanceMetrics;
use Modules\Finance\Infrastructure\EloquentCreditGuard;
use Modules\Finance\Infrastructure\EloquentIssuesInvoice;
use Modules\Finance\Infrastructure\EloquentReceiptNumberReserver;
use Modules\Finance\Infrastructure\EloquentRepCollectedToday;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreditGuard::class, EloquentCreditGuard::class);
        $this->app->singleton(IssuesInvoice::class, EloquentIssuesInvoice::class);
        $this->app->singleton(ReceiptNumberReserver::class, EloquentReceiptNumberReserver::class);
        $this->app->singleton(ChannelFinanceMetrics::class, EloquentChannelFinanceMetrics::class);
        $this->app->singleton(RepCollectedToday::class, EloquentRepCollectedToday::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $routes = __DIR__.'/../routes/api.php';
        if (is_file($routes)) {
            $this->loadRoutesFrom($routes);
        }
    }
}
