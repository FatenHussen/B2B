<?php

declare(strict_types=1);

namespace Modules\Loyalty\Application\Actions;

use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Loyalty\Application\Support\LoyaltyWallet;
use Modules\Loyalty\Domain\Enums\LoyaltyRewardStatus;
use Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use Modules\Loyalty\Domain\Models\LoyaltyReward;

final class RedeemLoyaltyReward
{
    public function __construct(
        private readonly LoyaltyWallet $wallet,
        private readonly RetailerShoppingContext $shopping,
        private readonly RetailerDirectory $retailers,
        private readonly RepDirectory $reps,
    ) {}

    /**
     * @param  array{reward_id: int}  $data
     * @return array{redemption_no: string}
     */
    public function __invoke(object $user, array $data): array
    {
        $kind = $this->shopping->isRetailer($user) ? 'retailer' : 'rep';
        $appUserId = (int) $user->getAuthIdentifier();
        $ownerId = $kind === 'retailer'
            ? ($this->retailers->profileIdForUser($appUserId) ?? 0)
            : $appUserId;

        $allowed = $kind === 'retailer' && $this->shopping->isRetailer($user)
            ? $this->shopping->for($user)['channel_ids']
            : array_values(array_filter([$this->reps->channelIdForUser($appUserId)]));

        // Lifted, per rule 10: `/app/loyalty/redeem` sets no tenant; the caller's channel ids isolate.
        $reward = LoyaltyReward::withoutGlobalScope('channel')
            ->whereKey((int) $data['reward_id'])
            ->whereIn('supply_channel_id', $allowed === [] ? [0] : $allowed)
            ->first();
        if ($reward === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        if ($reward->status === LoyaltyRewardStatus::Stopped) {
            throw DomainException::of(ErrorCode::ValidationFailed, __('loyalty.reward_unavailable'));
        }

        $channelId = (int) $reward->supply_channel_id;
        $cost = (int) $reward->points_cost;
        $account = $this->wallet->account($kind, $ownerId, $channelId);
        if ((int) $account->balance < $cost) {
            throw DomainException::of(ErrorCode::PlanLimitExceeded, __('loyalty.insufficient_points'));
        }

        if ((int) $reward->stock < 1) {
            throw DomainException::of(ErrorCode::ValidationFailed, __('loyalty.reward_unavailable'));
        }

        if ($reward->expires_at !== null && $reward->expires_at->lt(now()->startOfDay())) {
            throw DomainException::of(ErrorCode::ValidationFailed, __('loyalty.reward_unavailable'));
        }

        return Tenant::as($channelId, function () use ($reward, $account, $cost, $channelId): array {
            $reward->stock = (int) $reward->stock - 1;
            $reward->save();
            $this->wallet->debit($account, $cost, 'redeem', (int) $reward->id);
            $no = 'LY-'.$reward->id.'-'.$account->id.'-'.now()->format('His');
            LoyaltyRedemption::query()->create([
                'supply_channel_id' => $channelId,
                'account_id' => $account->id,
                'reward_id' => $reward->id,
                'redemption_no' => $no,
            ]);

            return ['redemption_no' => $no];
        });
    }
}
