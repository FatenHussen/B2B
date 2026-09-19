<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Cash this rep collected today (Damascus calendar), in minor units.
 *
 * Finance owns `wallet_transactions`. Identity's morning home (EP-RP-002) asks for a
 * number and receives a number — never a model, never a row.
 */
interface RepCollectedToday
{
    public function amount(int $repUserId): int;
}
