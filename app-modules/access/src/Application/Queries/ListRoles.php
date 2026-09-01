<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Access\Application\Services\PageSize;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Core\Http\ApiResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListRoles
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = QueryBuilder::for(AccessRole::class)
            ->allowedFilters(
                AllowedFilter::exact('system'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('id', 'name', 'created_at')
            ->defaultSort('id');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('label', 'like', '%'.$search.'%');
            });
        }

        $paginator = $query->paginate(PageSize::perPage($request));

        return ApiResponse::paginate($paginator, function (AccessRole $role): array {
            return [
                'id' => (int) $role->id,
                'key' => $role->name,
                'name' => $role->label ?? $role->name,
                'system' => $role->system,
                'status' => $role->status->value,
                'is_builtin' => $role->is_builtin,
                'permissions_count' => $role->permissions()->count(),
                'users_count' => $role->users()->count(),
            ];
        });
    }
}
