<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\RetailerGroup;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListRetailerGroups
{
    /**
     * @return LengthAwarePaginator<int, RetailerGroup>
     */
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 25), 100);

        return QueryBuilder::for(RetailerGroup::query()->with('members'))
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where('name', 'like', '%'.$value.'%');
                }),
            )
            ->defaultSort('name')
            ->paginate($perPage);
    }

    /**
     * @return array{id: int, name: string, retailer_ids: list<int>, members_count: int}
     */
    public function map(RetailerGroup $row): array
    {
        $ids = $row->members->pluck('retailer_id')->map(fn ($id) => (int) $id)->values()->all();

        return [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'retailer_ids' => $ids,
            'members_count' => count($ids),
        ];
    }

    /**
     * @return array{id: int, name: string, retailer_ids: list<int>, members_count: int}
     */
    public function show(int $id): array
    {
        $row = RetailerGroup::query()->with('members')->find($id);
        if ($row === null) {
            throw new DomainException(__('identity.not_found'), 'not_found', 404);
        }

        return $this->map($row);
    }
}
