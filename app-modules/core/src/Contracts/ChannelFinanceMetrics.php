<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ChannelFinanceMetrics
{
    /**
     * @return array{
     *     cash_collected: int,
     *     receivables: array{total: int, overdue: int}
     * }
     */
    public function snapshot(int $channelId, string $onDate): array;
}
