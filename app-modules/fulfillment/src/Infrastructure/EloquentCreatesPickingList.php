<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Infrastructure;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\CreatesPickingList;
use Modules\Fulfillment\Domain\Enums\PickingStatus;
use Modules\Fulfillment\Domain\Models\PickingLine;
use Modules\Fulfillment\Domain\Models\PickingList;
use Modules\Inventory\Domain\Models\WarehouseLocation;

final class EloquentCreatesPickingList implements CreatesPickingList
{
    public function __construct(private readonly CatalogProductLookup $products) {}

    public function create(array $payload): int
    {
        $existing = $this->idForSubOrder((int) $payload['sub_order_id']);
        if ($existing !== null) {
            return $existing;
        }

        $list = PickingList::query()->create([
            'sub_order_id' => $payload['sub_order_id'],
            'warehouse_id' => $payload['warehouse_id'],
            'channel_id' => $payload['channel_id'],
            'status' => PickingStatus::ToPick,
            'due_at' => now()->addHours(4),
        ]);

        $locations = WarehouseLocation::query()
            ->where('warehouse_id', $payload['warehouse_id'])
            ->orderBy('aisle')
            ->orderBy('shelf')
            ->get();

        $sorted = $payload['lines'];
        usort($sorted, function (array $a, array $b) use ($locations): int {
            $la = $locations->firstWhere('product_id', $a['product_id']); // locations aren't per product

            return 0;
        });

        foreach ($payload['lines'] as $line) {
            $snap = $this->products->snapshot((int) $line['product_id'], $line['variant_id'] ?? null);
            $loc = $locations->first();
            PickingLine::query()->create([
                'picking_list_id' => $list->id,
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'] ?? null,
                'qty_required' => $line['qty'],
                'qty_picked' => 0,
                'location_id' => $loc?->id,
                'barcode' => $snap['barcode'] ?? null,
            ]);
        }

        return (int) $list->id;
    }

    public function idForSubOrder(int $subOrderId): ?int
    {
        $id = PickingList::query()->where('sub_order_id', $subOrderId)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
