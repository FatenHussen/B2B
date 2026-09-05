<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class PickingShortageReported
{
    public function __construct(
        public readonly int $pickingListId,
        public readonly int $subOrderId,
        public readonly int $lineId,
        public readonly string $reason,
    ) {}
}
