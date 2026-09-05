<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Models\Brand;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListBrands
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Brand::class)
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));
    }
}
