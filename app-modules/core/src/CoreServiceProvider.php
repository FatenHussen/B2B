<?php

declare(strict_types=1);

namespace Modules\Core;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RequestsDualApproval;
use Modules\Core\Infrastructure\Audit\EloquentAuditTrail;
use Modules\Core\Infrastructure\Audit\EloquentRecordsAudit;
use Modules\Core\Infrastructure\EloquentRequestsDualApproval;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecordsAudit::class, EloquentRecordsAudit::class);
        $this->app->singleton(AuditTrail::class, EloquentAuditTrail::class);
        $this->app->singleton(RequestsDualApproval::class, EloquentRequestsDualApproval::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
