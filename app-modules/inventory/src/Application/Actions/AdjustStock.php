<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Actions;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class AdjustStock
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseDirectory $warehouses,
        private readonly CatalogProductLookup $products,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{product_id: int, variant_id?: int|null, warehouse_id: int, qty_delta: int, reason: string}  $data
     * @return array{movement_id: int, available: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $warehouseId = (int) $data['warehouse_id'];
        $channelId = (int) Tenant::currentId();
        if (! $this->warehouses->belongsToChannel($warehouseId, $channelId)) {
            InvalidFields::throw(['warehouse_id' => 'inventory.warehouse_not_found']);
        }
        if (! $this->products->exists((int) $data['product_id'])) {
            InvalidFields::throw(['product_id' => 'inventory.product_not_found']);
        }

        $result = $this->ledger->adjust(
            $warehouseId,
            (int) $data['product_id'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            (int) $data['qty_delta'],
            (string) $data['reason'],
            $actor,
        );

        // Catalog dual=true (EP-SC-051) is unmet: BE-C05's approval_requests live in
        // Access for IAM, and Core exposes no mutation hook. Inventory cannot import
        // Access models. The first request still executes. Documented for the FE handoff.

        $this->audit->record('inventory.adjust', $actor, 'stock_movement', $result['movement_id'], [
            'after' => $data,
        ], $channelId);

        return $result;
    }
}
