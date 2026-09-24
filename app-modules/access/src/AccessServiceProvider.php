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

        Gate::before(function (object $user, string $ability): ?bool {
            // App users have no Spatie roles. Their grant is their kind (`rp.*` / `rt.*`),
            // exposed through AccessCatalog. Returning a boolean here lets
            // `permission:…` middleware name the missing code for a cross-kind caller
            // (BF-09). Other guards fall through to Spatie HasRoles.
            if (! isset($user->kind)) {
                return null;
            }

            return in_array($ability, app(AccessCatalog::class)->permissionsFor($user), true);
        });

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
