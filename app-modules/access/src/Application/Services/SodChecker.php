<?php

declare(strict_types=1);

namespace Modules\Access\Application\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Access\Domain\Models\SodRule;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Core\Domain\Exceptions\DomainException;

final class SodChecker
{
    /**
     * @param  list<string>  $codes
     * @return list<array{code: string, permission_a: string, permission_b: string, reason: string}>
     */
    public function conflictsIn(array $codes, ?string $roleKey = null, ?Authenticatable $user = null): array
    {
        $set = array_fill_keys($codes, true);
        $hits = [];

        foreach (SodRule::query()->get() as $rule) {
            if (! isset($set[$rule->permission_a], $set[$rule->permission_b])) {
                continue;
            }
            if ($this->isExcepted($rule, $roleKey, $user)) {
                continue;
            }
            $hits[] = [
                'code' => $rule->code,
                'permission_a' => $rule->permission_a,
                'permission_b' => $rule->permission_b,
                'reason' => $rule->reason,
            ];
        }

        return $hits;
    }

    /**
     * @param  list<string>  $codes
     */
    public function assertCompatible(array $codes, ?string $roleKey = null): void
    {
        $hits = $this->conflictsIn($codes, $roleKey);
        if ($hits !== []) {
            throw new DomainException(__('access.sod_violation'), 'sod_violation', 403, ['conflicts' => $hits]);
        }
    }

    /**
     * @param  list<string>  $additional
     * @return list<array{code: string, permission_a: string, permission_b: string, reason: string}>
     */
    public function userConflicts(Authenticatable $user, array $additional = [], ?string $roleKey = null): array
    {
        $existing = [];
        if (method_exists($user, 'getAllPermissions')) {
            $existing = $user->getAllPermissions()->pluck('name')->all();
        }

        return $this->conflictsIn([...$existing, ...$additional], $roleKey, $user);
    }

    public function wouldViolate(Authenticatable $user, string $permission): bool
    {
        return $this->userConflicts($user, [$permission]) !== [];
    }

    /**
     * @param  list<string>  $codes
     */
    public function assertCatalogCodes(array $codes): void
    {
        foreach ($codes as $code) {
            if (! PermissionCatalog::exists($code)) {
                throw new DomainException(__('access.unknown_permission'), 'validation_failed', 422, [
                    'permissions' => [__('access.unknown_permission')],
                ]);
            }
        }
    }

    private function isExcepted(SodRule $rule, ?string $roleKey, ?Authenticatable $user): bool
    {
        $exceptions = $rule->exceptions ?? [];
        $keys = $exceptions['role_keys'] ?? [];
        if (! is_array($keys)) {
            return false;
        }

        if ($roleKey !== null && in_array($roleKey, $keys, true)) {
            return true;
        }

        if ($user !== null && method_exists($user, 'getRoleNames')) {
            foreach ($user->getRoleNames() as $name) {
                if (in_array((string) $name, $keys, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
