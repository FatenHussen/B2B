<?php

declare(strict_types=1);

namespace Modules\Access\Infrastructure;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Modules\Access\Domain\PermissionCatalog;

final class GuardUserLocator
{
    public function find(string $guard, int $id): ?Authenticatable
    {
        $providerName = config('auth.guards.'.$guard.'.provider');
        if (! is_string($providerName) || $providerName === '') {
            return null;
        }

        $provider = Auth::createUserProvider($providerName);
        $user = $provider?->retrieveById($id);

        return $user instanceof Authenticatable ? $user : null;
    }

    public function findForPermission(string $permission, int $id): ?Authenticatable
    {
        $row = PermissionCatalog::get($permission);
        $system = $row['system'] ?? 'platform';

        return $this->find(PermissionCatalog::guardForSystem($system), $id);
    }

    public function morphAlias(object $user): string
    {
        $map = Relation::morphMap();
        $class = $user::class;
        $alias = array_search($class, $map, true);

        return is_string($alias) ? $alias : $class;
    }
}
