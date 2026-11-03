<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Domain\Events\StockShortageAllocated;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Core\Support\WarehouseScope;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\ShortageAllocation;
use Modules\Ordering\Domain\Models\ShortageAllocationLine;
use Modules\Ordering\Domain\Models\SubOrderLine;

final class AllocateStockShortage
{
    private const OPEN = [
        SubOrderStatus::Confirmed,
        SubOrderStatus::Assigned,
        SubOrderStatus::Accepted,
        SubOrderStatus::Processing,
    ];

    public function __construct(
        private readonly EditSubOrderLines $editLines,
        private readonly WarehouseDirectory $warehouses,
    ) {}

    /**
     * @param  array{product_id: int, variant_id?: int|null, qty_available: int, reason: string}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $actor, array $data): array
    {
        $channelId = (int) Tenant::currentId();
        $warehouseId = WarehouseScope::currentId()
            ?? $this->warehouses->defaultIdForChannel($channelId);
        if ($warehouseId === null) {
            throw new DomainException(__('inventory.warehouse_not_found'), 'not_found', 404);
        }

        $productId = (int) $data['product_id'];
        $variantId = isset($data['variant_id']) ? (int) $data['variant_id'] : null;
        $available = (int) $data['qty_available'];

        $candidates = SubOrderLine::query()
            ->where('product_id', $productId)
            ->when(
                $variantId === null,
                fn ($q) => $q->whereNull('variant_id'),
                fn ($q) => $q->where('variant_id', $variantId),
            )
            ->whereHas('subOrder', function ($q) use ($channelId): void {
                $q->where('channel_id', $channelId)
                    ->whereIn('status', array_map(fn (SubOrderStatus $s) => $s->value, self::OPEN));
            })
            ->with(['subOrder'])
            ->get()
            ->sortBy(fn (SubOrderLine $line) => $line->subOrder->created_at->timestamp)
            ->values();

        if ($candidates->isEmpty()) {
            throw new DomainException(__('ordering.not_found'), 'validation_failed', 422);
        }

        return DB::transaction(function () use ($actor, $channelId, $warehouseId, $productId, $variantId, $available, $data, $candidates): array {
            $allocation = ShortageAllocation::query()->create([
                'channel_id' => $channelId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'qty_available' => $available,
                'reason' => $data['reason'],
                'status' => 'open',
            ]);

            $remaining = $available;
            $outLines = [];
            $subOrderIds = [];

            foreach ($candidates as $line) {
                $requested = (int) $line->qty;
                $allocated = min($requested, $remaining);
                $remaining -= $allocated;

                $lineStatus = $allocated < $requested ? 'awaiting_retailer' : 'accepted';
                if ($allocated !== $requested) {
                    $this->editLines->__invoke($actor, (int) $line->sub_order_id, [
                        'changes' => [[
                            'line_id' => (int) $line->id,
                            'qty' => $allocated,
                            'removed' => $allocated === 0,
                        ]],
                        'reason' => 'stock_shortage',
                    ]);
                }

                ShortageAllocationLine::query()->create([
                    'shortage_allocation_id' => $allocation->id,
                    'sub_order_id' => (int) $line->sub_order_id,
                    'sub_order_line_id' => (int) $line->id,
                    'qty_requested' => $requested,
                    'qty_allocated' => $allocated,
                    'status' => $lineStatus,
                ]);

                $outLines[] = [
                    'sub_order_id' => (int) $line->sub_order_id,
                    'sub_order_line_id' => (int) $line->id,
                    'qty_requested' => $requested,
                    'qty_allocated' => $allocated,
                    'status' => $lineStatus,
                ];
                $subOrderIds[] = (int) $line->sub_order_id;
            }

            event(new StockShortageAllocated(
                (int) $allocation->id,
                $channelId,
                $productId,
                $variantId,
                array_values(array_unique($subOrderIds)),
            ));

            return [
                'allocation_id' => (int) $allocation->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'qty_available' => $available,
                'lines' => $outLines,
            ];
        });
    }
}
