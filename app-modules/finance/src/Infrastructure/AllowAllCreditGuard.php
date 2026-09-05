<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Modules\Core\Contracts\CreditGuard;

final class AllowAllCreditGuard implements CreditGuard
{
    public function assertWithinLimit(int $retailerId, int $channelId, int $amountMinor): void {}
}
