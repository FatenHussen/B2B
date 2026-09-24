<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Channel credit limit for a retailer profile — Identity's retailer 360 (EP-SC-140A / EP-SC-164).
 */
interface RetailerCreditSnapshot
{
    /**
     * @return array{credit_limit: int|null, grace_days: int|null, on_exceed: string|null}
     */
    public function forRetailer(int $retailerId, int $channelId): array;

    /**
     * Sum of remaining balances on open invoices for this retailer in the channel.
     */
    public function outstanding(int $retailerId, int $channelId): int;
}
