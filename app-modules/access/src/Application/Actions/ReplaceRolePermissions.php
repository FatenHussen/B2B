<?php

declare(strict_types=1);

namespace Modules\Access\Application\Actions;

use Modules\Access\Application\Services\SodChecker;
use Modules\Access\Domain\Enums\AccessChangeStatus;
use Modules\Access\Domain\Enums\AccessChangeType;
use Modules\Access\Domain\Enums\RoleStatus;
use Modules\Access\Domain\Models\AccessChangeRequest;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Exceptions\DomainException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class ReplaceRolePermissions
{
    public function __construct(
        private readonly SodChecker $sod,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{permissions: list<string>, reason: string}  $data
     * @return array{changed: list<string>, sod_conflicts: list<array<string, mixed>>}
     */
    public function __invoke(object $actor, int $roleId, array $data): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $role = AccessRole::query()->find($roleId);
        if ($role === null) {
            throw new DomainException(__('access.not_found'), 'not_found', 404);
        }

        $codes = array_values(array_unique($data['permissions']));
        $this->sod->assertCatalogCodes($codes);
        foreach ($codes as $code) {
            $row = PermissionCatalog::get($code);
            if ($row !== null && $row['system'] !== $role->system) {
                throw new DomainException(__('access.permission_system_mismatch'), 'validation_failed', 422);
            }
        }

        $current = $role->permissions->pluck('name')->all();
        $changed = array_values(array_unique(array_merge(
            array_diff($current, $codes),
            array_diff($codes, $current),
        )));
        $conflicts = $this->sod->conflictsIn($codes);

        if ($role->status === RoleStatus::Active) {
            AccessChangeRequest::query()->create([
                'type' => AccessChangeType::RolePermissions,
                'status' => AccessChangeStatus::Pending,
                'permission' => 'ad.iam.role_create',
                'action' => 'role.permissions',
                'payload' => ['role_id' => $role->id, 'permissions' => $codes, 'reason' => $data['reason']],
                'requester_id' => (int) $actor->getAuthIdentifier(),
                'subject_id' => $role->id,
            ]);

            $this->audit->record(
                'role.permissions.request',
                $actor,
                'role',
                (int) $role->id,
                ['before' => $current, 'after' => $codes, 'reason' => $data['reason']],
            );

            return ['changed' => $changed, 'sod_conflicts' => $conflicts];
        }

        $permissions = [];
        foreach ($codes as $code) {
            $permissions[] = Permission::findOrCreate($code, $role->guard_name);
        }
        $role->syncPermissions($permissions);

        $this->audit->record(
            'role.permissions',
            $actor,
            'role',
            (int) $role->id,
            ['before' => $current, 'after' => $codes, 'reason' => $data['reason']],
        );

        return ['changed' => $changed, 'sod_conflicts' => $conflicts];
    }
}
