<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Support;

use Modules\Core\Contracts\RepCommercialLimits;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;

final class ChannelRepPresenter
{
    public function __construct(
        private readonly RepDutyLookup $duty,
        private readonly RepCommercialLimits $limits,
    ) {}

    /**
     * @return array{
     *     id: int,
     *     name: string|null,
     *     phone: string|null,
     *     status: string,
     *     zone_ids: list<int>,
     *     on_duty: bool,
     *     max_discount_percent: int,
     *     max_cash_hold: int
     * }
     */
    public function present(RepProfile $profile, AppUser $user): array
    {
        $userId = (int) $user->id;
        $channelId = (int) $profile->channel_id;

        return [
            'id' => $userId,
            'name' => is_string($user->name) ? $user->name : null,
            'phone' => is_string($user->phone) ? $user->phone : null,
            'status' => $profile->status->value,
            'zone_ids' => $profile->zoneIds(),
            'on_duty' => $this->duty->isOnDuty($userId),
            'max_discount_percent' => $this->limits->maxDiscountPercent($channelId, $userId),
            'max_cash_hold' => $this->limits->maxCashHold($channelId, $userId),
        ];
    }
}
