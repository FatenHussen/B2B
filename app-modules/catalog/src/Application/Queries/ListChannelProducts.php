<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Models\Product;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListChannelProducts
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Product::class)
            ->allowedFilters(
                AllowedFilter::exact('brand_id'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('zone_id', function ($query, $value): void {
                    $query->whereHas('zones', fn ($q) => $q->where('zone_id', $value));
                }),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->where('name_ar', 'like', '%'.$value.'%')
                            ->orWhere('sku', 'like', '%'.$value.'%')
                            ->orWhere('barcode', 'like', '%'.$value.'%');
                    });
                }),
            )
            ->defaultSort('-created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));
    }
}
