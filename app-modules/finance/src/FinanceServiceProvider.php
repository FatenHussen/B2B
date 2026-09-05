<?php

declare(strict_types=1);

namespace Modules\Finance;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\CreditGuard;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\ReceiptNumberReserver;
use Modules\Finance\Infrastructure\AllowAllCreditGuard;
use Modules\Finance\Infrastructure\EloquentIssuesInvoice;
use Modules\Finance\Infrastructure\EloquentReceiptNumberReserver;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreditGuard::class, AllowAllCreditGuard::class);
        $this->app->singleton(IssuesInvoice::class, EloquentIssuesInvoice::class);
        $this->app->singleton(ReceiptNumberReserver::class, EloquentReceiptNumberReserver::class);
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
