<?php

declare(strict_types=1);

namespace Modules\Loyalty\Application\Queries;

use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Loyalty\Application\Support\LoyaltyWallet;
use Modules\Loyalty\Domain\Models\LoyaltyAccount;
use Modules\Loyalty\Domain\Models\LoyaltyReward;
use Modules\Loyalty\Domain\Models\LoyaltyTransaction;

final class ShowAppLoyalty
{
    public function __construct(
        private readonly LoyaltyWallet $wallet,
        private readonly RetailerShoppingContext $shopping,
        private readonly RepSellingContext $selling,
        private readonly RetailerDirectory $retailers,
        private readonly RepDirectory $reps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user): array
    {
        $kind = $this->shopping->isRetailer($user) ? 'retailer' : ($this->selling->isRep($user) ? 'rep' : 'rep');
        $appUserId = (int) $user->getAuthIdentifier();
        $ownerId = $kind === 'retailer'
            ? ($this->retailers->profileIdForUser($appUserId) ?? 0)
            : $appUserId;

        $accounts = LoyaltyAccount::query()
            ->where('owner_kind', $kind)
            ->where('owner_id', $ownerId)
            ->get();

        $channelIds = $accounts->map(fn (LoyaltyAccount $a) => (int) $a->supply_channel_id)->filter()->unique()->values()->all();
        if ($channelIds === []) {
            if ($kind === 'rep') {
                $channelId = $this->reps->channelIdForUser($appUserId);
                $channelIds = $channelId === null ? [] : [$channelId];
            } elseif ($this->shopping->isRetailer($user)) {
                $channelIds = $this->shopping->for($user)['channel_ids'];
            }
        }

        $points = (int) $accounts->sum(fn (LoyaltyAccount $row) => (int) $row->balance);
        $primaryChannel = (int) ($channelIds[0] ?? 0);
        $tier = $primaryChannel > 0 ? $this->wallet->tierFor($primaryChannel, $points) : 'bronze';
        $next = $primaryChannel > 0 ? $this->wallet->nextTier($primaryChannel, $points) : ['name' => 'silver', 'remaining' => 1000];

        $accountIds = $accounts->map(fn (LoyaltyAccount $a) => (int) $a->id)->all();
        $history = $accountIds === []
            ? []
            : LoyaltyTransaction::query()
                ->whereIn('account_id', $accountIds)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (LoyaltyTransaction $row) => [
                    'at' => $row->created_at?->timezone('Asia/Damascus')->toDateString(),
                    'delta' => (int) $row->points,
                    'reason' => $row->reason,
                ])
                ->all();

        $rewards = [];
        if ($channelIds !== []) {
            // Lifted, per rule 10: `/app/loyalty` sets no tenant; whereIn channel ids is the caller's own set.
            $rewards = LoyaltyReward::withoutGlobalScope('channel')
                ->whereIn('supply_channel_id', $channelIds)
                ->where(function ($q): void {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
                })
                ->orderBy('points_cost')
                ->get()
                ->map(fn (LoyaltyReward $row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'points_cost' => (int) $row->points_cost,
                ])
                ->all();
        }

        return [
            'points' => $points,
            'tier' => $tier,
            'next_tier' => $next,
            'history' => $history,
            'rewards' => $rewards,
        ];
    }
}
