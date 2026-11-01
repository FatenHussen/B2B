<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class ListRepScheduledOrders
{
    public function __construct(
        private readonly RetailerDirectory $retailers,
        private readonly ReferenceDirectory $refs,
        private readonly IssuesInvoice $invoices,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(object $user, ?string $date): array
    {
        // acrossChannels(), per rule 10 (BE-C12): `/app/rep/*` sets no tenant; `rep_id`
        // below is the isolation — a rep sees their own deliveries and no one else's.
        $query = SubOrder::query()
            ->acrossChannels()
            ->where('rep_id', $user->getAuthIdentifier())
            ->where('status', SubOrderStatus::Postponed);

        if ($date) {
            $query->whereDate('scheduled_at', $date);
        }

        return $query->get()->map(function (SubOrder $row) {
            $shop = $this->retailers->find((int) $row->retailer_id);
            $phone = $shop !== null ? $this->retailers->phone((int) $row->retailer_id) : null;

            return [
                'id' => (int) $row->id,
                'sub_order_no' => $row->sub_order_no,
                'invoice_no' => $this->invoices->forSubOrder((int) $row->id)['no'] ?? null,
                'shop_logo' => null,
                'shop' => $shop['shop_name'] ?? null,
                'zone' => $row->zone_id ? $this->refs->zoneName((int) $row->zone_id) : null,
                'address' => $shop['address'] ?? null,
                'phone' => $phone,
                'created_at' => $row->created_at?->timezone('Asia/Damascus')->toIso8601String(),
                'scheduled_at' => $row->scheduled_at?->timezone('Asia/Damascus')->toIso8601String(),
                'status' => $row->status->value,
                'color' => 'amber',
            ];
        })->all();
    }
}
