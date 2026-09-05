<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class DeliveryCompleted
{
    public function __construct(
        public readonly int $subOrderId,
        public readonly int $invoiceId,
        public readonly string $invoiceNo,
    ) {}
}
