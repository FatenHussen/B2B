<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface CreditGuard
{
    /**
     * Throws DomainException 423 credit_limit_exceeded when the retailer×channel limit blocks.
     * Unconfigured limits must allow.
     */
    public function assertWithinLimit(int $retailerId, int $channelId, int $amountMinor): void;
}
