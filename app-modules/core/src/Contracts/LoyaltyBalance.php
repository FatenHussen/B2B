<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface LoyaltyBalance
{
    /**
     * Wallet snapshot for the home badge. Kind is `retailer` or `rep`.
     *
     * @return array{points: int, tier: string, next_tier: array{name: string, remaining: int}|null}
     */
    public function snapshot(int $appUserId, string $kind): array;
}
