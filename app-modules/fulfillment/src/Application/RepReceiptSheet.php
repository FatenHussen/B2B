<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Application;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Fulfillment\Domain\Models\Handover;

/**
 * The rep's warehouse day: one row per sub-order still waiting for the rep's
 * code, plus the shelf list those orders collapse into.
 *
 * A handover reaches this list only after the warehouse has opened it, so
 * `parties.warehouse` is already confirmed and `parties.rep` is still pending.
 */
final class RepReceiptSheet
{
    public function __construct(
        private readonly SubOrderLifecycle $orders,
        private readonly CatalogProductLookup $products,
        private readonly IssuesInvoice $invoices,
    ) {}

    /**
     * @param  iterable<int, Handover>  $handovers
     * @return array{orders: list<array<string, mixed>>, items: list<array<string, mixed>>, item_count: int}
     */
    public function present(iterable $handovers): array
    {
        $orders = [];
        /** @var array<string, array<string, mixed>> $items */
        $items = [];

        foreach ($handovers as $handover) {
            foreach ($handover->items as $item) {
                $subOrderId = (int) $item->sub_order_id;
                $header = $this->orders->header($subOrderId);
                $lines = $this->lines($subOrderId);
                foreach ($lines as $line) {
                    $this->accumulate($items, $line);
                }
                $invoice = $this->invoices->forSubOrder($subOrderId);
                $orders[] = [
                    'sub_order_id' => $subOrderId,
                    'order_no' => $header['sub_order_no'] ?? null,
                    'invoice_no' => $invoice['no'] ?? null,
                    'shop' => $header['shop_name'] ?? null,
                    'zone' => $header['zone_name'] ?? null,
                    'channel' => $header['channel_name'] ?? null,
                    'created_at' => $header['created_at'] ?? null,
                    'handover_id' => (int) $handover->id,
                    'total' => (int) ($header['total'] ?? 0),
                    'parties' => [
                        'rep' => 'pending',
                        'warehouse' => 'confirmed',
                    ],
                    'lines' => $lines,
                ];
            }
        }

        return [
            'orders' => $orders,
            'items' => array_values($items),
            'item_count' => count($items),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lines(int $subOrderId): array
    {
        $out = [];
        foreach ($this->orders->lines($subOrderId) as $line) {
            $variantId = $line['variant_id'];
            $snap = $this->products->snapshot((int) $line['product_id'], $variantId);
            $out[] = [
                'id' => (int) $line['id'],
                'product_id' => (int) $line['product_id'],
                'variant_id' => $variantId,
                'name' => $line['name'] !== '' ? $line['name'] : (string) ($snap['name'] ?? ''),
                'brand' => $line['brand'] ?? ($snap['brand'] ?? null),
                'variant' => $snap['variant'] ?? null,
                'sale_unit' => $snap['sale_unit'] ?? null,
                'unit_factors' => $snap['unit_factors'] ?? [],
                'image' => $snap['image'] ?? null,
                'qty' => (int) $line['qty'],
                'unit_price' => (int) $line['unit_price'],
                'line_total' => (int) $line['line_total'],
            ];
        }

        return $out;
    }

    /**
     * Shelf quantity is the sum of the same product and variant across every
     * shop on this pickup. Prices stay on the order; this row is what to carry.
     *
     * @param  array<string, array<string, mixed>>  $items
     * @param  array<string, mixed>  $line
     */
    private function accumulate(array &$items, array $line): void
    {
        $variant = $line['variant_id'] === null ? '' : (string) $line['variant_id'];
        $key = $line['product_id'].':'.$variant;
        if (! isset($items[$key])) {
            $items[$key] = [
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'name' => $line['name'],
                'variant' => $line['variant'],
                'sale_unit' => $line['sale_unit'],
                'image' => $line['image'],
                'qty' => 0,
            ];
        }
        $items[$key]['qty'] = (int) $items[$key]['qty'] + (int) $line['qty'];
    }
}
