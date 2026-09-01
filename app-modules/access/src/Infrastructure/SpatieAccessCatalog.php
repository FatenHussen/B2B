<?php

declare(strict_types=1);

namespace Modules\Access\Infrastructure;

use BackedEnum;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Core\Contracts\AccessCatalog;

final class SpatieAccessCatalog implements AccessCatalog
{
    public function permissionsFor(object $user): array
    {
        if (isset($user->kind)) {
            $kind = $user->kind instanceof BackedEnum ? $user->kind->value : (string) $user->kind;

            return $this->permissionsForAppKind($kind);
        }

        if (method_exists($user, 'getAllPermissions')) {
            return $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return [];
    }

    public function rolesFor(object $user): array
    {
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->values()->all();
        }

        return [];
    }

    public function permissionsForAppKind(string $kind): array
    {
        return match ($kind) {
            'retailer' => PermissionCatalog::codesStartingWith('rt.'),
            'rep' => PermissionCatalog::codesStartingWith('rp.'),
            default => [],
        };
    }
}
