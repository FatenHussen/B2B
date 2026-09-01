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
use Modules\Core\Support\InvalidFields;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class CreateRole
{
    public function __construct(
        private readonly SodChecker $sod,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{name: string, key: string, system: string, description?: string|null, permissions?: list<string>, copy_from_role_id?: int|null}  $data
     * @return array{id: int, status: string, sod_conflicts: list<array<string, mixed>>}
     */
    public function __invoke(object $actor, array $data): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $key = $data['key'];
        $system = $data['system'];
        $guard = PermissionCatalog::guardForSystem($system);

        if (AccessRole::query()->where('name', $key)->where('guard_name', $guard)->exists()) {
            InvalidFields::throw(['key' => 'access.role_key_taken']);
        }

        $codes = array_values(array_unique($data['permissions'] ?? []));
        if (isset($data['copy_from_role_id']) && $data['copy_from_role_id']) {
            $source = AccessRole::query()->find((int) $data['copy_from_role_id']);
            if ($source !== null) {
                $codes = array_values(array_unique([
                    ...$codes,
                    ...$source->permissions->pluck('name')->all(),
                ]));
            }
        }

        $this->sod->assertCatalogCodes($codes);
        foreach ($codes as $code) {
            $row = PermissionCatalog::get($code);
            if ($row !== null && $row['system'] !== $system) {
                InvalidFields::throw(['permissions' => 'access.permission_system_mismatch']);
            }
        }

        $conflicts = $this->sod->conflictsIn($codes);

        $role = AccessRole::query()->create([
            'name' => $key,
            'label' => $data['name'],
            'guard_name' => $guard,
            'system' => $system,
            'status' => RoleStatus::Draft,
            'is_builtin' => false,
            'description' => $data['description'] ?? null,
            'created_by' => (int) $actor->getAuthIdentifier(),
            'team_id' => 0,
        ]);

        $permissions = [];
        foreach ($codes as $code) {
            $permissions[] = Permission::findOrCreate($code, $guard);
        }
        $role->syncPermissions($permissions);

        AccessChangeRequest::query()->create([
            'type' => AccessChangeType::RoleCreate,
            'status' => AccessChangeStatus::Pending,
            'permission' => 'ad.iam.role_create',
            'action' => 'role.create',
            'payload' => ['role_id' => $role->id, 'permissions' => $codes],
            'requester_id' => (int) $actor->getAuthIdentifier(),
            'subject_id' => $role->id,
        ]);

        $this->audit->record(
            'role.create',
            $actor,
            'role',
            (int) $role->id,
            ['after' => ['key' => $key, 'status' => RoleStatus::Draft->value]],
        );

        return [
            'id' => (int) $role->id,
            'status' => RoleStatus::Draft->value,
            'sod_conflicts' => $conflicts,
        ];
    }
}
