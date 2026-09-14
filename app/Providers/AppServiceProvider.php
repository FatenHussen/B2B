<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\WarehouseUser;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope is a dev dependency: absent after `composer install --no-dev`, and
        // wanted only on a local machine even when present. Registering it here, behind
        // both checks, is what lets the same bootstrap/providers.php boot in every
        // environment. `laravel/telescope` is also in composer.json `dont-discover`, so
        // package discovery cannot register it behind this guard's back.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        app()->setLocale('en');

        Relation::enforceMorphMap([
            'platform_user' => PlatformUser::class,
            'channel_user' => ChannelUser::class,
            'warehouse_user' => WarehouseUser::class,
            'app_user' => AppUser::class,
        ]);

        Gate::before(function ($user, string $ability) {
            if ($user instanceof PlatformUser && $user->hasRole('platform_admin')) {
                return true;
            }

            return null;
        });
    }
}
