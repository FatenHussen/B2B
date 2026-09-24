<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ChannelOrderMetrics
{
    /**
     * fill_rate is an integer at scale 10^4 (1.00 = 10000). Times are minutes.
     *
     * @return array{
     *     sales: int,
     *     orders_by_status: array<string, int>,
     *     avg_order_value: int,
     *     avg_confirm_time: int,
     *     avg_delivery_time: int,
     *     fill_rate: int,
     *     retailers: array{active: int, registered: int, new: int},
     *     charts?: array<string, mixed>,
     *     alerts?: list<array<string, mixed>>,
     *     margins?: array{by_product: list<array<string, mixed>>, by_zone: list<array<string, mixed>>}
     * }
     */
    public function snapshot(int $channelId, string $onDate): array;
}
