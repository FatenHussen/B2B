<?php

declare(strict_types=1);

namespace Modules\Loyalty\Application\Listeners;

use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Domain\Events\DeliveryCompleted;
use Modules\Core\Support\Tenant;
use Modules\Loyalty\Application\Support\LoyaltyWallet;
use Modules\Loyalty\Domain\Models\LoyaltyRuleSet;

final class EarnOnDeliveryCompleted
{
    public function __construct(
        private readonly LoyaltyWallet $wallet,
        private readonly SubOrderLifecycle $orders,
        private readonly RetailerDirectory $retailers,
        private readonly RepDirectory $reps,
    ) {}

    public function handle(DeliveryCompleted $event): void
    {
        $header = $this->orders->header($event->subOrderId);
        if ($header === null) {
            return;
        }

        $channelId = (int) $header['channel_id'];
        $total = (int) $header['total'];
        $rules = Tenant::as($channelId, fn () => LoyaltyRuleSet::query()->first());
        $retailerRules = is_array($rules?->retailer_rules) ? $rules->retailer_rules : [];
        $repRules = is_array($rules?->rep_rules) ? $rules->rep_rules : [];

        $retailerPoints = $this->wallet->pointsFor($retailerRules, 'delivery_completed', $total);
        if ($retailerPoints === 0) {
            $retailerPoints = $this->wallet->pointsFor($retailerRules, 'invoice_paid', $total);
        }
        $retailerId = (int) $header['retailer_id'];
        if ($retailerPoints > 0 && $retailerId > 0 && $this->retailers->exists($retailerId)) {
            $this->wallet->credit(
                'retailer',
                $retailerId,
                $channelId,
                $retailerPoints,
                'invoice_paid',
                'sub_order',
                $event->subOrderId,
            );
        }

        $repUserId = $header['rep_user_id'] ?? $header['rep_id'];
        if (is_int($repUserId) && $repUserId > 0 && $this->reps->exists($repUserId)) {
            $repPoints = $this->wallet->pointsFor($repRules, 'delivery_completed', $total);
            if ($repPoints > 0) {
                $this->wallet->credit(
                    'rep',
                    $repUserId,
                    $channelId,
                    $repPoints,
                    'delivery_completed',
                    'sub_order',
                    $event->subOrderId,
                );
            }
        }
    }
}
