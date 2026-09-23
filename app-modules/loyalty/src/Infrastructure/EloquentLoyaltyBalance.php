<?php

declare(strict_types=1);

namespace Modules\Loyalty\Infrastructure;

use Modules\Core\Contracts\LoyaltyBalance;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Loyalty\Application\Support\LoyaltyWallet;
use Modules\Loyalty\Domain\Models\LoyaltyAccount;

final class EloquentLoyaltyBalance implements LoyaltyBalance
{
    public function __construct(
        private readonly LoyaltyWallet $wallet,
        private readonly RetailerDirectory $retailers,
        private readonly RepDirectory $reps,
    ) {}

    public function snapshot(int $appUserId, string $kind): array
    {
        $ownerId = $kind === 'retailer'
            ? ($this->retailers->profileIdForUser($appUserId) ?? 0)
            : $appUserId;

        if ($ownerId < 1) {
            return ['points' => 0, 'tier' => 'bronze', 'next_tier' => ['name' => 'silver', 'remaining' => 1000]];
        }

        $accounts = LoyaltyAccount::query()
            ->where('owner_kind', $kind)
            ->where('owner_id', $ownerId)
            ->get();

        $points = (int) $accounts->sum(fn (LoyaltyAccount $row) => (int) $row->balance);
        $channelId = $kind === 'rep'
            ? $this->reps->channelIdForUser($appUserId)
            : (int) ($accounts->first()?->supply_channel_id ?? 0);

        $tier = 'bronze';
        $next = ['name' => 'silver', 'remaining' => 1000];
        if ($channelId > 0) {
            $tier = $this->wallet->tierFor($channelId, $points);
            $next = $this->wallet->nextTier($channelId, $points);
        }

        return [
            'points' => $points,
            'tier' => $tier,
            'next_tier' => $next,
        ];
    }
}
