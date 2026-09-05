<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

final class HandoverConfirmedByRep
{
    public function __construct(
        public readonly int $handoverId,
        public readonly int $repUserId,
        public readonly array $subOrderIds,
    ) {}
}
