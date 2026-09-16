<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Inventory\Domain\Models\StockReorderPoint;

final class UpsertReorderPoints
{
    public function __construct(
        private readonly WarehouseDirectory $warehouses,
        private readonly CatalogProductLookup $products,
    ) {}

    /**
     * @param  array{items: list<array{product_id: int, warehouse_id: int, variant_id?: int|null, point: int}>}  $data
     * @return array{updated: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $channelId = (int) Tenant::currentId();

        return DB::transaction(function () use ($data, $channelId): array {
            $updated = 0;

            foreach ($data['items'] as $i => $item) {
                $warehouseId = (int) $item['warehouse_id'];
                if (! $this->warehouses->belongsToChannel($warehouseId, $channelId)) {
                    InvalidFields::throw(["items.$i.warehouse_id" => 'inventory.warehouse_not_found']);
                }
                if (! $this->products->exists((int) $item['product_id'])) {
                    InvalidFields::throw(["items.$i.product_id" => 'inventory.product_not_found']);
                }

                StockReorderPoint::query()->updateOrCreate(
                    [
                        'warehouse_id' => $warehouseId,
                        'product_id' => (int) $item['product_id'],
                        'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : 0,
                    ],
                    ['point' => (int) $item['point']],
                );
                $updated++;
            }

            return ['updated' => $updated];
        });
    }
}
