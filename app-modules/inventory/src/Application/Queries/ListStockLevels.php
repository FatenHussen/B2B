<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Inventory\Domain\Models\StockBalance;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListStockLevels
{
    public function __construct(
        private readonly CatalogProductLookup $products,
        private readonly WarehouseDirectory $warehouses,
    ) {}

    public function __invoke(): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 25), 100);

        return QueryBuilder::for(StockBalance::class)
            ->allowedFilters(
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_id'),
            )
            ->defaultSort('id')
            ->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function map(StockBalance $row): array
    {
        $snap = $this->products->snapshot((int) $row->product_id, (int) $row->variant_id ?: null);
        $warehouse = $this->warehouses->find((int) $row->warehouse_id);

        return [
            'product' => [
                'id' => (int) $row->product_id,
                'name_ar' => $snap['name'] ?? null,
            ],
            'variant' => ((int) $row->variant_id) > 0 ? ['id' => (int) $row->variant_id] : null,
            'warehouse' => $warehouse !== null
                ? ['id' => $warehouse['id'], 'name' => $warehouse['name']]
                : ['id' => (int) $row->warehouse_id, 'name' => ''],
            'available' => $row->available(),
            'reserved' => (int) $row->reserved,
            'in_transit' => (int) $row->in_transit,
            'damaged' => (int) $row->damaged,
        ];
    }
}
