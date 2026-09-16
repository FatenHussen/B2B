<?php

declare(strict_types=1);

namespace Modules\Ordering\Infrastructure;

use Modules\Core\Contracts\ChannelOrderMetrics;
use Modules\Core\Support\Tenant;
use Modules\Ordering\Domain\Models\SubOrder;

final class EloquentChannelOrderMetrics implements ChannelOrderMetrics
{
    public function snapshot(int $channelId, string $onDate): array
    {
        return Tenant::as($channelId, function () use ($onDate): array {
            $rows = SubOrder::query()->whereDate('created_at', $onDate)->get();
            $sales = 0;
            $byStatus = [];
            foreach ($rows as $row) {
                $sales += (int) $row->total;
                $status = $row->status->value;
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            }

            $count = $rows->count();
            $retailers = $rows->pluck('retailer_id')->unique()->count();

            return [
                'sales' => $sales,
                'orders_by_status' => $byStatus,
                'avg_order_value' => $count > 0 ? intdiv($sales, $count) : 0,
                'avg_confirm_time' => 0,
                'avg_delivery_time' => 0,
                'fill_rate' => 0,
                'retailers' => [
                    'active' => $retailers,
                    'registered' => $retailers,
                    'new' => $retailers,
                ],
            ];
        });
    }
}
