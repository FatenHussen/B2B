<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class ListRepScheduledOrders
{
    public function __construct(private readonly RetailerDirectory $retailers) {}

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
                'shop_logo' => null,
                'shop' => $shop['shop_name'] ?? null,
                'address' => $shop['address'] ?? null,
                'phone' => $phone,
                'scheduled_at' => $row->scheduled_at?->timezone('Asia/Damascus')->toIso8601String(),
                'status' => $row->status->value,
                'color' => 'amber',
            ];
        })->all();
    }
}
