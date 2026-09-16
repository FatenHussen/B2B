<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RequestsDualApproval;
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
        private readonly RequestsDualApproval $dual,
    ) {}

    /**
     * @param  array{product_id: int, variant_id?: int|null, warehouse_id: int, qty_delta: int, reason: string, approval_request_id?: int|null, approval_reason?: string|null}  $data
     * @return array<string, int>
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

        $payload = [
            'product_id' => (int) $data['product_id'],
            'variant_id' => isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            'warehouse_id' => $warehouseId,
            'qty_delta' => (int) $data['qty_delta'],
            'reason' => (string) $data['reason'],
        ];

        return DB::transaction(function () use ($actor, $data, $payload, $warehouseId, $channelId): array {
            $decision = $this->dual->gate(
                $actor,
                'sc.inventory.adjust',
                'inventory.adjust',
                $payload,
                isset($data['approval_request_id']) ? (int) $data['approval_request_id'] : null,
                isset($data['approval_reason']) ? (string) $data['approval_reason'] : null,
            );

            if (! $decision->execute) {
                return ['approval_request_id' => (int) $decision->approvalRequestId];
            }

            $result = $this->ledger->adjust(
                $warehouseId,
                (int) $data['product_id'],
                isset($data['variant_id']) ? (int) $data['variant_id'] : null,
                (int) $data['qty_delta'],
                (string) $data['reason'],
                $actor,
            );

            $this->audit->record('inventory.adjust', $actor, 'stock_movement', $result['movement_id'], [
                'after' => $payload,
                'approval_request_id' => $decision->approvalRequestId,
            ], $channelId);

            return $result;
        });
    }
}
