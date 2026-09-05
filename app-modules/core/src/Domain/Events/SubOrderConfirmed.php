<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class SubOrderConfirmed
{
    /**
     * @param  list<array{product_id: int, variant_id: int|null, qty: int}>  $lines
     */
    public function __construct(
        public readonly int $subOrderId,
        public readonly int $channelId,
        public readonly int $warehouseId,
        public readonly array $lines,
    ) {}
}
