<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Models\AuditLog;
use Spatie\Permission\Models\Permission;

final class ListPermissionHolders
{
    /**
     * @return array{roles: list<array{id: int, key: string}>, users: list<array{id: int, name: string}>, recent_usage: list<array{at: string, actor: int|null, action: string}>}
     */
    public function __invoke(string $code): array
    {
        $permission = Permission::query()->where('name', $code)->first();
        if ($permission === null) {
            throw new DomainException(__('access.unknown_permission'), 'not_found', 404);
        }

        $roles = AccessRole::query()
            ->whereHas('permissions', fn ($q) => $q->where('permissions.id', $permission->id))
            ->get()
            ->map(fn (AccessRole $role) => [
                'id' => (int) $role->id,
                'key' => $role->name,
            ])
            ->values()
            ->all();

        $users = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->where('role_has_permissions.permission_id', $permission->id)
            ->select('model_has_roles.model_id as id')
            ->distinct()
            ->limit(50)
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => '#'.$row->id])
            ->values()
            ->all();

        $recent = AuditLog::query()
            ->where(function ($q) use ($code): void {
                $q->where('action', 'like', '%'.$code.'%')
                    ->orWhere('action', 'simulate');
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'at' => $log->created_at?->timezone('Asia/Damascus')->toIso8601String(),
                'actor' => $log->actor_id,
                'action' => $log->action,
            ])
            ->all();

        return [
            'roles' => $roles,
            'users' => $users,
            'recent_usage' => $recent,
        ];
    }
}
