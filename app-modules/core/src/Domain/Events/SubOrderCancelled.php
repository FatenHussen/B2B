<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class SubOrderCancelled
{
    public function __construct(
        public readonly int $subOrderId,
        public readonly int $channelId,
        public readonly string $reason,
    ) {}
}
