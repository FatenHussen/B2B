<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Contracts\ChannelFinanceMetrics;
use Modules\Core\Contracts\ChannelOrderMetrics;
use Modules\Core\Support\Tenant;
use Modules\Reporting\Domain\Models\DailySnapshot;

final class GenerateDailySnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $channelId,
        public readonly string $onDate,
    ) {
        $this->onQueue('reports');
    }

    public function handle(ChannelOrderMetrics $orders, ChannelFinanceMetrics $finance): void
    {
        Tenant::as($this->channelId, function () use ($orders, $finance): void {
            $order = $orders->snapshot($this->channelId, $this->onDate);
            $money = $finance->snapshot($this->channelId, $this->onDate);

            $payload = [
                'kpis' => [
                    'sales' => $order['sales'],
                    'orders_by_status' => $order['orders_by_status'],
                    'cash_collected' => $money['cash_collected'],
                    'receivables' => $money['receivables'],
                    'retailers' => $order['retailers'],
                    'avg_order_value' => $order['avg_order_value'],
                    'avg_confirm_time' => $order['avg_confirm_time'],
                    'avg_delivery_time' => $order['avg_delivery_time'],
                    'fill_rate' => $order['fill_rate'],
                ],
                'alerts' => [],
                'charts' => [
                    'daily_sales' => [['date' => $this->onDate, 'sales' => $order['sales']]],
                    'by_zone' => [],
                    'top_products' => [],
                    'top_retailers' => [],
                    'rep_performance' => [],
                    'heatmap' => [],
                ],
                'reports' => [
                    'sales' => [
                        'rows' => [[
                            'date' => $this->onDate,
                            'sales' => $order['sales'],
                            'orders' => array_sum($order['orders_by_status']),
                        ]],
                        'totals' => ['sales' => $order['sales']],
                    ],
                    'finance' => [
                        'rows' => [[
                            'date' => $this->onDate,
                            'cash_collected' => $money['cash_collected'],
                            'receivables' => $money['receivables']['total'],
                        ]],
                        'totals' => [
                            'cash_collected' => $money['cash_collected'],
                            'receivables' => $money['receivables']['total'],
                        ],
                    ],
                ],
                'margins' => ['by_product' => [], 'by_zone' => []],
            ];

            DailySnapshot::query()->updateOrCreate(
                [
                    'supply_channel_id' => $this->channelId,
                    'snapshot_date' => $this->onDate,
                ],
                ['payload' => $payload],
            );
        });
    }
}
