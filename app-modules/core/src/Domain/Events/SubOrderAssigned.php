<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class SubOrderAssigned
{
    public function __construct(
        public readonly int $subOrderId,
        public readonly int $repUserId,
        public readonly int $channelId,
    ) {}
}
