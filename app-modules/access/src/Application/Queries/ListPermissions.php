<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Access\Application\Services\PageSize;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Core\Http\ApiResponse;
use Spatie\Permission\Models\Permission;

final class ListPermissions
{
    public function __invoke(Request $request): JsonResponse
    {
        $system = $request->input('filter.system');
        $severity = $request->input('filter.severity');
        $search = trim((string) $request->input('search', ''));

        $rows = [];
        foreach (PermissionCatalog::all() as $code => $row) {
            if (is_string($system) && $system !== '' && $row['system'] !== $system) {
                continue;
            }
            if (is_string($severity) && $severity !== '' && $row['severity'] !== $severity) {
                continue;
            }
            if ($search !== '' && ! str_contains($code, $search) && ! str_contains($row['name_ar'], $search)) {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'name_ar' => $row['name_ar'],
                'system' => $row['system'],
                'module' => $row['module'],
                'severity' => $row['severity'],
                'dual_approval' => $row['dual_approval'],
                'delegatable' => $row['delegatable'],
                'roles_count' => 0,
                'users_count' => 0,
            ];
        }

        $paginator = PageSize::paginate($rows, $request);
        $pageCodes = collect($paginator->items())->pluck('code')->all();
        $counts = $this->countsFor($pageCodes);

        $paginator->setCollection(
            collect($paginator->items())->map(function (array $row) use ($counts): array {
                $row['roles_count'] = $counts[$row['code']]['roles'] ?? 0;
                $row['users_count'] = $counts[$row['code']]['users'] ?? 0;

                return $row;
            })
        );

        return ApiResponse::paginate($paginator);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, array{roles: int, users: int}>
     */
    private function countsFor(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $permissions = Permission::query()->whereIn('name', $codes)->get(['id', 'name']);
        $idsByName = $permissions->pluck('id', 'name');
        $ids = $permissions->pluck('id');

        $roleCounts = AccessRole::query()
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->whereIn('role_has_permissions.permission_id', $ids)
            ->select('role_has_permissions.permission_id', DB::raw('COUNT(DISTINCT roles.id) as c'))
            ->groupBy('role_has_permissions.permission_id')
            ->pluck('c', 'permission_id');

        $userCounts = DB::table('model_has_permissions')
            ->whereIn('permission_id', $ids)
            ->select('permission_id', DB::raw('COUNT(DISTINCT model_id) as c'))
            ->groupBy('permission_id')
            ->pluck('c', 'permission_id');

        $out = [];
        foreach ($idsByName as $name => $id) {
            $out[$name] = [
                'roles' => (int) ($roleCounts[$id] ?? 0),
                'users' => (int) ($userCounts[$id] ?? 0),
            ];
        }

        return $out;
    }
}
