<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Ordering\Domain\Enums\AssignmentStatus;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderAssignment;

final class ListRepAssignments
{
    public function __construct(
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
        private readonly RetailerDirectory $retailers,
        private readonly CatalogProductLookup $products,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(object $user): array
    {
        $ids = SubOrderAssignment::query()
            ->where('rep_id', $user->getAuthIdentifier())
            ->where('status', AssignmentStatus::Pending)
            ->pluck('sub_order_id');

        // acrossChannels(), per rule 10: `/app/rep/*` sets no tenant. The ids were
        // already restricted to this rep's assignments before this query runs.
        return SubOrder::query()->acrossChannels()->with('lines')->whereIn('id', $ids)->where('status', SubOrderStatus::Assigned)->get()
            ->map(function (SubOrder $row) {
                $shop = $this->retailers->find((int) $row->retailer_id);

                return [
                    'id' => (int) $row->id,
                    'sub_order_no' => $row->sub_order_no,
                    'shop' => $shop['shop_name'] ?? null,
                    'zone' => $row->zone_id ? $this->refs->zoneName((int) $row->zone_id) : null,
                    'channel' => $this->channels->name((int) $row->channel_id),
                    'invoice_no' => null,
                    'created_at' => $row->created_at?->timezone('Asia/Damascus')->toIso8601String(),
                    'total' => (int) $row->total,
                    'lines' => $this->lines($row),
                ];
            })->all();
    }

    /**
     * The invoice sheet on an assignment card. No invoice exists yet — these are
     * the order lines, and the card number stays `sub_order_no` while `invoice_no` is null.
     *
     * @return list<array<string, mixed>>
     */
    private function lines(SubOrder $row): array
    {
        $out = [];
        foreach ($row->lines as $line) {
            $variantId = $line->variant_id ? (int) $line->variant_id : null;
            $snap = $this->products->snapshot((int) $line->product_id, $variantId);
            $out[] = [
                'id' => (int) $line->id,
                'name' => (string) ($snap['name'] ?? ''),
                'brand' => $snap['brand'] ?? null,
                'variant' => $snap['variant'] ?? null,
                'sale_unit' => $snap['sale_unit'] ?? null,
                'unit_factors' => $snap['unit_factors'] ?? [],
                'image' => $snap['image'] ?? null,
                'qty' => (int) $line->qty,
                'unit_price' => (int) $line->unit_price,
                'line_total' => (int) $line->line_total,
            ];
        }

        return $out;
    }
}
