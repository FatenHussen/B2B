<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Finance applies BR-13 credit impact when a return is approved on the channel.
 */
interface AppliesReturnCredit
{
    /**
     * @param  list<array{line_id: int, qty: int}>  $lines  sub_order line id + returned qty
     */
    public function apply(int $subOrderId, array $lines, string $reason, object $actor): void;
}
