<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Finished orders for one rep in a Damascus calendar month. Identity's account
 * sheet needs the two counts and must not read ordering tables.
 */
interface RepMonthOutcomes
{
    /**
     * @return array{completed: int, undelivered: int}
     */
    public function inMonth(int $repUserId, string $yearMonth): array;
}
