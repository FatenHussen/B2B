<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Modules\Access\Domain\Models\AccessRole;
use Modules\Access\Infrastructure\GuardUserLocator;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Exceptions\DomainException;
use Spatie\Permission\PermissionRegistrar;

final class RevokeRoleAssignment
{
    public function __construct(
        private readonly GuardUserLocator $users,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{user_id: int, role_id: int, reason: string}  $data
     * @return array{success: bool}
     */
    public function __invoke(object $actor, array $data): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $role = AccessRole::query()->find((int) $data['role_id']);
        if ($role === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        $user = $this->users->find($role->guard_name, (int) $data['user_id']);
        if ($user === null || ! method_exists($user, 'removeRole')) {
            throw new DomainException(__('access.user_not_found'), 'not_found', 404);
        }

        $user->removeRole($role);

        $this->audit->record(
            'role.revoke',
            $actor,
            'role',
            (int) $role->id,
            ['before' => ['user_id' => (int) $data['user_id'], 'role_id' => $role->id], 'reason' => $data['reason']],
        );

        return ['success' => true];
    }
}
