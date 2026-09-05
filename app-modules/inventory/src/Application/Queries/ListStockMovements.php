<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Inventory\Domain\Models\StockMovement;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListStockMovements
{
    public function __invoke(): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 25), 100);

        return QueryBuilder::for(StockMovement::class)
            ->allowedFilters([
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_id'),
            ])
            ->defaultSort('-id')
            ->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function map(StockMovement $row): array
    {
        return [
            'id' => (int) $row->id,
            'at' => $row->at?->timezone('Asia/Damascus')->toIso8601String(),
            'type' => $row->type->value,
            'qty_before' => (int) $row->qty_before,
            'qty_after' => (int) $row->qty_after,
        ];
    }
}
