<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class StockShortageAllocated
{
    /**
     * @param  list<int>  $subOrderIds
     */
    public function __construct(
        public readonly int $allocationId,
        public readonly int $channelId,
        public readonly int $productId,
        public readonly ?int $variantId,
        public readonly array $subOrderIds,
    ) {}
}
