<?php

declare(strict_types=1);

namespace Modules\Access;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Access\Console\SyncPermissionsCommand;
use Modules\Access\Console\VerifyPermissionsCommand;
use Modules\Access\Domain\Models\TempGrant;
use Modules\Access\Infrastructure\GuardUserLocator;
use Modules\Access\Infrastructure\SpatieAccessCatalog;
use Modules\Core\Contracts\AccessCatalog;

class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccessCatalog::class, SpatieAccessCatalog::class);
        $this->app->singleton(GuardUserLocator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPermissionsCommand::class,
                VerifyPermissionsCommand::class,
            ]);
        }

        Gate::after(function (object $user, string $ability, mixed $result): ?bool {
            if ($result === true) {
                return null;
            }
            if (! method_exists($user, 'getAuthIdentifier')) {
                return null;
            }

            $alias = app(GuardUserLocator::class)->morphAlias($user);
            $exists = TempGrant::query()
                ->where('grantee_type', $alias)
                ->where('grantee_id', (int) $user->getAuthIdentifier())
                ->where('permission', $ability)
                ->get()
                ->contains(fn (TempGrant $grant) => $grant->isLive());

            return $exists ? true : null;
        });
    }
}
