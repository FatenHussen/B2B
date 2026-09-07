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
    public function register(): void {}

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
