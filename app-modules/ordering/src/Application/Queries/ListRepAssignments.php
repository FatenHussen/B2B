<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Ordering\Domain\Enums\AssignmentStatus;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderAssignment;

final class ListRepAssignments
{
    public function __construct(
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
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

        return SubOrder::query()->whereIn('id', $ids)->where('status', SubOrderStatus::Assigned)->get()
            ->map(function (SubOrder $row) {
                $shop = RetailerProfile::query()->find($row->retailer_id);

                return [
                    'id' => (int) $row->id,
                    'sub_order_no' => $row->sub_order_no,
                    'shop' => $shop?->shop_name,
                    'zone' => $row->zone_id ? $this->refs->zoneName((int) $row->zone_id) : null,
                    'channel' => $this->channels->name((int) $row->channel_id),
                    'invoice_no' => null,
                    'created_at' => $row->created_at?->timezone('Asia/Damascus')->toIso8601String(),
                ];
            })->all();
    }
}
