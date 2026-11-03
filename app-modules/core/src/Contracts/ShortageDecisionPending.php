<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * True while a warehouse SKU shortage allocation awaits the retailer's
 * accept_partial|cancel decision for this sub-order (EP-WH-063 / EP-RT-036).
 */
interface ShortageDecisionPending
{
    public function blocksSubOrder(int $subOrderId): bool;
}
