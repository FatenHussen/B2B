<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Models\ShortageAllocationLine;
use Modules\Ordering\Domain\Models\SubOrder;

final class ShowRetailerShortage
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly CatalogProductLookup $products,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $subOrderId): array
    {
        $retailerId = $this->shopping->for($user)['retailer_id'];
        $sub = SubOrder::query()
            ->acrossChannels()
            ->where('retailer_id', $retailerId)
            ->with('lines')
            ->find($subOrderId);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $pending = ShortageAllocationLine::query()
            ->where('sub_order_id', $subOrderId)
            ->where('status', 'awaiting_retailer')
            ->get();
        if ($pending->isEmpty()) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $lines = [];
        $totalBefore = 0;
        $totalAfter = 0;
        foreach ($pending as $row) {
            $orderLine = $sub->lines->firstWhere('id', $row->sub_order_line_id);
            if ($orderLine === null) {
                continue;
            }
            $productId = (int) $orderLine->product_id;
            $variantId = $orderLine->variant_id ? (int) $orderLine->variant_id : null;
            $snap = $this->products->snapshot($productId, $variantId);
            $unit = (int) $orderLine->unit_price;
            $totalBefore += $unit * (int) $row->qty_requested;
            $totalAfter += $unit * (int) $row->qty_allocated;
            $lines[] = [
                'sub_order_line_id' => (int) $row->sub_order_line_id,
                'product_id' => $productId,
                'name' => (string) ($snap['name'] ?? ''),
                'qty_requested' => (int) $row->qty_requested,
                'qty_allocated' => (int) $row->qty_allocated,
            ];
        }

        return [
            'sub_order_id' => $subOrderId,
            'status' => 'awaiting_decision',
            'lines' => $lines,
            'total_before' => $totalBefore,
            'total_after' => $totalAfter,
        ];
    }
}
