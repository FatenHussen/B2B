<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class ListRepScheduledOrders
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(object $user, ?string $date): array
    {
        $query = SubOrder::query()
            ->where('rep_id', $user->getAuthIdentifier())
            ->where('status', SubOrderStatus::Postponed);

        if ($date) {
            $query->whereDate('scheduled_at', $date);
        }

        return $query->get()->map(function (SubOrder $row) {
            $shop = RetailerProfile::query()->find($row->retailer_id);
            $phone = $shop !== null ? AppUser::query()->whereKey($shop->app_user_id)->value('phone') : null;

            return [
                'shop_logo' => null,
                'shop' => $shop?->shop_name,
                'address' => $shop?->address,
                'phone' => $phone,
                'scheduled_at' => $row->scheduled_at?->timezone('Asia/Damascus')->toIso8601String(),
                'status' => $row->status->value,
                'color' => 'amber',
            ];
        })->all();
    }
}
