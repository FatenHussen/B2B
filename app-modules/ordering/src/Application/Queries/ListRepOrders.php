<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Ordering\Domain\Models\SubOrder;

final class ListRepOrders
{
    public function __construct(
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
        private readonly RetailerDirectory $retailers,
        private readonly IssuesInvoice $invoices,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(object $user): array
    {
        // acrossChannels(), per rule 10: `/app/rep/*` sets no tenant; `rep_id`
        // below is the isolation — a rep sees their own sub-orders and no one else's.
        return SubOrder::query()
            ->acrossChannels()
            ->where('rep_id', $user->getAuthIdentifier())
            ->orderByDesc('id')
            ->get()
            ->map(function (SubOrder $row) {
                $shop = $this->retailers->find((int) $row->retailer_id);
                $invoice = $this->invoices->forSubOrder((int) $row->id);

                return [
                    'id' => (int) $row->id,
                    'sub_order_no' => $row->sub_order_no,
                    'invoice_no' => $invoice['no'] ?? null,
                    'created_at' => $row->created_at?->timezone('Asia/Damascus')->toIso8601String(),
                    'status' => $row->status->value,
                    'shop' => $shop['shop_name'] ?? null,
                    'zone' => $row->zone_id ? $this->refs->zoneName((int) $row->zone_id) : null,
                    'channel' => $this->channels->name((int) $row->channel_id),
                    'total' => (int) $row->total,
                ];
            })->all();
    }
}
