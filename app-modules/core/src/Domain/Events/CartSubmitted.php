<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class CartSubmitted
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $retailerId,
        public readonly string $source,
    ) {}
}
