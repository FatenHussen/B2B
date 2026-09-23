<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A loyalty redemption code Pricing can consume at quote time without
 * importing the Loyalty module (same Domain layer).
 */
interface RedeemableDiscount
{
    /**
     * @return array{redemption_no: string, channel_id: int, points: int}|null
     */
    public function consume(string $redemptionNo, int $channelId): ?array;
}
