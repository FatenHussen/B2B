<?php

declare(strict_types=1);

namespace Modules\Ordering\Infrastructure;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\ChannelOrderMetrics;
use Modules\Core\Support\Tenant;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\Models\SubOrderLine;

final class EloquentChannelOrderMetrics implements ChannelOrderMetrics
{
    public function snapshot(int $channelId, string $onDate): array
    {
        return Tenant::as($channelId, function () use ($onDate): array {
            $rows = SubOrder::query()->whereDate('created_at', $onDate)->get();
            $sales = 0;
            $byStatus = [];
            $byZone = [];
            foreach ($rows as $row) {
                $sales += (int) $row->total;
                $status = $row->status->value;
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
                $zid = $row->zone_id ? (int) $row->zone_id : 0;
                if ($zid > 0) {
                    $byZone[$zid] = ($byZone[$zid] ?? 0) + (int) $row->total;
                }
            }

            $count = $rows->count();
            $retailers = $rows->pluck('retailer_id')->unique()->count();

            $topProducts = SubOrderLine::query()
                ->whereIn('sub_order_id', $rows->pluck('id'))
                ->selectRaw('product_id, sum(qty) as qty_sum, sum(line_total) as sales_sum')
                ->groupBy('product_id')
                ->orderByDesc('sales_sum')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'product_id' => (int) $r->product_id,
                    'qty' => (int) $r->getAttribute('qty_sum'),
                    'sales' => (int) $r->getAttribute('sales_sum'),
                ])
                ->all();

            $topRetailers = $rows
                ->groupBy('retailer_id')
                ->map(fn ($g, $rid) => [
                    'retailer_id' => (int) $rid,
                    'sales' => (int) $g->sum('total'),
                    'orders' => $g->count(),
                ])
                ->sortByDesc('sales')
                ->take(10)
                ->values()
                ->all();

            $repPerformance = $rows
                ->filter(fn (SubOrder $s) => $s->rep_id !== null)
                ->groupBy('rep_id')
                ->map(fn ($g, $rid) => [
                    'rep_id' => (int) $rid,
                    'orders' => $g->count(),
                    'sales' => (int) $g->sum('total'),
                ])
                ->values()
                ->all();

            $confirmMinutes = $this->avgStageMinutes($rows->pluck('id')->all(), SubOrderStatus::Confirmed->value);
            $deliveryMinutes = $this->avgStageMinutes($rows->pluck('id')->all(), SubOrderStatus::Delivered->value);

            $delivered = (int) ($byStatus[SubOrderStatus::Delivered->value] ?? 0);
            $fillRate = $count > 0 ? intdiv($delivered * 10000, $count) : 0;

            $pendingOver = SubOrder::query()
                ->where('status', SubOrderStatus::Pending)
                ->where('created_at', '<', now()->subMinutes(30))
                ->count();

            $alerts = [];
            if ($pendingOver > 0) {
                $alerts[] = [
                    'type' => 'waiting_orders',
                    'count' => $pendingOver,
                    'action_url' => '/orders?filter[status]=pending',
                ];
            }

            return [
                'sales' => $sales,
                'orders_by_status' => $byStatus,
                'avg_order_value' => $count > 0 ? intdiv($sales, $count) : 0,
                'avg_confirm_time' => $confirmMinutes,
                'avg_delivery_time' => $deliveryMinutes,
                'fill_rate' => $fillRate,
                'retailers' => [
                    'active' => $retailers,
                    'registered' => $retailers,
                    'new' => $retailers,
                ],
                'charts' => [
                    'by_zone' => collect($byZone)->map(fn ($sales, $zid) => [
                        'zone_id' => (int) $zid,
                        'sales' => (int) $sales,
                    ])->values()->all(),
                    'top_products' => $topProducts,
                    'top_retailers' => $topRetailers,
                    'rep_performance' => $repPerformance,
                    'heatmap' => [],
                ],
                'alerts' => $alerts,
                'margins' => [
                    'by_product' => array_map(
                        fn (array $p) => [
                            'product_id' => $p['product_id'],
                            'sales' => $p['sales'],
                            'margin' => $p['sales'],
                        ],
                        $topProducts,
                    ),
                    'by_zone' => collect($byZone)->map(fn ($sales, $zid) => [
                        'zone_id' => (int) $zid,
                        'sales' => (int) $sales,
                        'margin' => (int) $sales,
                    ])->values()->all(),
                ],
            ];
        });
    }

    /**
     * @param  list<int>  $subOrderIds
     */
    private function avgStageMinutes(array $subOrderIds, string $stage): int
    {
        if ($subOrderIds === []) {
            return 0;
        }
        $events = SubOrderEvent::query()
            ->whereIn('sub_order_id', $subOrderIds)
            ->where('stage', $stage)
            ->get(['sub_order_id', 'at']);
        if ($events->isEmpty()) {
            return 0;
        }
        $created = SubOrder::query()->whereIn('id', $subOrderIds)->pluck('created_at', 'id');
        $sum = 0;
        $n = 0;
        foreach ($events as $event) {
            $start = $created->get($event->sub_order_id);
            if (! $start instanceof Carbon || ! $event->at instanceof Carbon) {
                continue;
            }
            $sum += max(0, $start->diffInMinutes($event->at));
            $n++;
        }

        return $n > 0 ? intdiv($sum, $n) : 0;
    }
}
