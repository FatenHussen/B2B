<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface HandoverGuard
{
    public function isConfirmedForSubOrder(int $subOrderId): bool;
}
