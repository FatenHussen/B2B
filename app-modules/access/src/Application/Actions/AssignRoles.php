<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Modules\Access\Application\Services\SodChecker;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Access\Infrastructure\GuardUserLocator;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Exceptions\DomainException;
use Spatie\Permission\PermissionRegistrar;

final class AssignRoles
{
    public function __construct(
        private readonly SodChecker $sod,
        private readonly GuardUserLocator $users,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{user_ids: list<int>, role_id: int, expires_at?: string|null, reason: string}  $data
     * @return array{assigned: list<int>, rejected: list<array{user_id: int, reason: string}>}
     */
    public function __invoke(object $actor, array $data): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $role = AccessRole::query()->find((int) $data['role_id']);
        if ($role === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        $rolePermissions = $role->permissions->pluck('name')->all();
        $assigned = [];
        $rejected = [];

        foreach ($data['user_ids'] as $userId) {
            $user = $this->users->find($role->guard_name, (int) $userId);
            if ($user === null || ! method_exists($user, 'assignRole')) {
                $rejected[] = ['user_id' => (int) $userId, 'reason' => __('access.user_not_found')];

                continue;
            }

            $conflicts = $this->sod->userConflicts($user, $rolePermissions, $role->name);
            if ($conflicts !== []) {
                $rejected[] = ['user_id' => (int) $userId, 'reason' => __('access.sod_violation')];

                continue;
            }

            $user->assignRole($role);
            $assigned[] = (int) $userId;
        }

        $this->audit->record(
            'role.assign',
            $actor,
            'role',
            (int) $role->id,
            [
                'after' => ['role_id' => $role->id, 'assigned' => $assigned, 'rejected' => $rejected],
                'reason' => $data['reason'],
            ],
        );

        return ['assigned' => $assigned, 'rejected' => $rejected];
    }
}
