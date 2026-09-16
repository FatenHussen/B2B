<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Inventory\Domain\Enums\TransferStatus;
use Modules\Inventory\Domain\Models\StockTransfer;
use Modules\Inventory\Domain\Models\StockTransferLine;

final class CreateStockTransfer
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseDirectory $warehouses,
        private readonly CatalogProductLookup $products,
    ) {}

    /**
     * @param  array{from_warehouse_id: int, to_warehouse_id: int, lines: list<array{product_id: int, variant_id?: int|null, qty: int}>}  $data
     * @return array{id: int, status: string}
     */
    public function __invoke(object $actor, array $data): array
    {
        $channelId = (int) Tenant::currentId();
        $from = (int) $data['from_warehouse_id'];
        $to = (int) $data['to_warehouse_id'];
        if ($from === $to) {
            InvalidFields::throw(['to_warehouse_id' => 'inventory.same_warehouse']);
        }
        if (! $this->warehouses->belongsToChannel($from, $channelId)) {
            InvalidFields::throw(['from_warehouse_id' => 'inventory.warehouse_not_found']);
        }
        if (! $this->warehouses->belongsToChannel($to, $channelId)) {
            InvalidFields::throw(['to_warehouse_id' => 'inventory.warehouse_not_found']);
        }

        $lines = [];
        foreach ($data['lines'] as $i => $line) {
            if (! $this->products->exists((int) $line['product_id'])) {
                InvalidFields::throw(["lines.$i.product_id" => 'inventory.product_not_found']);
            }
            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
                'qty' => (int) $line['qty'],
            ];
        }

        return DB::transaction(function () use ($from, $to, $lines, $actor): array {
            $transfer = StockTransfer::query()->create([
                'from_warehouse_id' => $from,
                'to_warehouse_id' => $to,
                'status' => TransferStatus::Sent,
            ]);

            foreach ($lines as $line) {
                StockTransferLine::query()->create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'] ?: 0,
                    'qty' => $line['qty'],
                ]);
            }

            $this->ledger->transferSent($from, $to, $lines, (int) $transfer->id, $actor);

            return ['id' => (int) $transfer->id, 'status' => TransferStatus::Sent->value];
        });
    }
}
