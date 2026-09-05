<?php

declare(strict_types=1);

namespace Modules\Access\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Access\Domain\Enums\RoleStatus;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Access\Domain\Models\SodRule;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Access\Domain\Support\AccessMatrix;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    /** @var array<string, string> */
    private const LABELS = [
        'platform_admin' => 'مدير المنصة',
        'channel_manager' => 'مدير القناة',
        'sales_manager' => 'مدير المبيعات',
        'catalog_manager' => 'مدير الكتالوج',
        'accountant' => 'محاسب',
        'warehouse_keeper' => 'أمين مستودع',
        'retailer' => 'تاجر',
        'rep' => 'مندوب',
    ];

    /** @var array<string, string> */
    private const ROLE_SYSTEM = [
        'platform_admin' => 'platform',
        'channel_manager' => 'channel',
        'sales_manager' => 'channel',
        'catalog_manager' => 'channel',
        'accountant' => 'channel',
        'warehouse_keeper' => 'warehouse',
        'retailer' => 'app',
        'rep' => 'app',
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId(0);

        foreach (PermissionCatalog::all() as $code => $row) {
            $this->permission($code, PermissionCatalog::guardForSystem($row['system']));
        }

        foreach (['platform', 'channel', 'warehouse', 'app', 'web'] as $guard) {
            foreach (AccessMatrix::permissions() as $name) {
                $this->permission($name, $guard);
            }
        }

        $registrar->forgetCachedPermissions();

        $grants = PermissionCatalog::builtinGrants();
        $legacy = AccessMatrix::roles();

        foreach (self::ROLE_SYSTEM as $key => $system) {
            $guard = PermissionCatalog::guardForSystem($system);
            $role = AccessRole::query()
                ->where('name', $key)
                ->where('guard_name', $guard)
                ->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', 0))
                ->first();

            if ($role === null) {
                $role = AccessRole::query()->create([
                    'name' => $key,
                    'guard_name' => $guard,
                    'team_id' => 0,
                    'label' => self::LABELS[$key],
                    'system' => $system,
                    'status' => RoleStatus::Active,
                    'is_builtin' => true,
                ]);
            } else {
                $role->forceFill([
                    'team_id' => 0,
                    'label' => self::LABELS[$key],
                    'system' => $system,
                    'status' => RoleStatus::Active,
                    'is_builtin' => true,
                ])->save();
            }

            $names = array_values(array_unique([
                ...($grants[$key] ?? []),
                ...($legacy[$key] ?? []),
            ]));
            $permissions = [];
            foreach ($names as $name) {
                $permissions[] = $this->permission($name, $guard);
            }
            $role->syncPermissions($permissions);
        }

        SodRule::query()->firstOrCreate(
            ['code' => 'SOD-01'],
            [
                'permission_a' => 'sc.orders.confirm',
                'permission_b' => 'sc.finance.payment',
                'reason' => 'لا يجتمع تأكيد الطلب مع تسجيل الدفعة',
                'exceptions' => ['role_keys' => ['channel_manager']],
            ],
        );

        $registrar->forgetCachedPermissions();
    }

    private function permission(string $name, string $guard): Permission
    {
        return Permission::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => $guard],
        );
    }
}
