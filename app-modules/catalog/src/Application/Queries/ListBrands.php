<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Models\Brand;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class ListBrands
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Brand::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::callback('activity_type_id', function ($query, $value): void {
                    $query->whereHas('activityTypes', fn ($q) => $q->where('activity_type_id', $value));
                }),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->where('name_ar', 'like', '%'.$value.'%')
                            ->orWhere('name_en', 'like', '%'.$value.'%');
                    });
                }),
            )
            ->allowedSorts(
                AllowedSort::field('order'),
                AllowedSort::field('created_at'),
                AllowedSort::field('name_ar'),
            )
            ->defaultSort('order')
            ->paginate(min((int) $request->get('per_page', 25), 100));
    }
}
