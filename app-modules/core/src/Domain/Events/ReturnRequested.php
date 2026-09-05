<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class ReturnRequested
{
    public function __construct(
        public readonly int $returnRequestId,
        public readonly int $subOrderId,
        public readonly int $channelId,
    ) {}
}
