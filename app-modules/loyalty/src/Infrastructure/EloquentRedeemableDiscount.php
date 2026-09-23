<?php

declare(strict_types=1);

namespace Modules\Loyalty\Infrastructure;

use Modules\Core\Contracts\RedeemableDiscount;
use Modules\Core\Support\Tenant;
use Modules\Loyalty\Domain\Models\LoyaltyRedemption;

final class EloquentRedeemableDiscount implements RedeemableDiscount
{
    public function consume(string $redemptionNo, int $channelId): ?array
    {
        return Tenant::as($channelId, function () use ($redemptionNo, $channelId): ?array {
            $row = LoyaltyRedemption::query()
                ->where('redemption_no', $redemptionNo)
                ->whereNull('consumed_at')
                ->first();
            if ($row === null) {
                return null;
            }

            $row->consumed_at = now();
            $row->save();

            return [
                'redemption_no' => $row->redemption_no,
                'channel_id' => $channelId,
                'points' => 0,
            ];
        });
    }
}
