<?php

declare(strict_types=1);

namespace Modules\Reference;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Reference\Infrastructure\EloquentReferenceDirectory;

class ReferenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReferenceDirectory::class, EloquentReferenceDirectory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
