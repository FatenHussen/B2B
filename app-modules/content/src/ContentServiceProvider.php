<?php

namespace Modules\Content;

use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $migrations = __DIR__.'/../database/migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }
}
