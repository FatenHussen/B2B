<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * What this rep can still collect from one shop. Identity prints it on the
 * shop sheet and must not read finance tables.
 */
interface RepShopReceivable
{
    public function openTotal(int $repUserId, int $retailerId): int;
}
