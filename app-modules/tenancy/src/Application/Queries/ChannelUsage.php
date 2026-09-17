<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Queries;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Contracts\ChannelOrderMetrics;
use Modules\Core\Contracts\ChannelUserCounter;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\Models\ChannelProvisionJob;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;

/**
 * BE-T11 — EP-AD-056. A 30-day series and the plan-limit usage, from real counters.
 *
 * An empty or all-zero series is a valid answer: a channel that did nothing returns
 * thirty zero days, never an error and never an empty body, and no gap is filled with a
 * guess (requirement 2).
 *
 * Every number crosses a module boundary as a number, through a Core contract: orders and
 * GMV from Ordering, SKUs from Catalog, reps and dashboard users from Identity. The two
 * Tenancy owns — warehouses and failed provisioning jobs — are read here.
 */
final class ChannelUsage
{
    public function __construct(
        private readonly ChannelOrderMetrics $orders,
        private readonly ChannelLimits $limits,
        private readonly RepDirectory $reps,
        private readonly CatalogProductLookup $catalog,
        private readonly ChannelUserCounter $users,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SupplyChannel $channel, string $range = '30d'): array
    {
        $channelId = (int) $channel->id;
        $days = $this->days($range);
        $today = Carbon::today('Asia/Damascus');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i)->toDateString();
            $snapshot = $this->orders->snapshot($channelId, $day);
            $series[] = [
                'day' => $day,
                'orders' => (int) array_sum($snapshot['orders_by_status'] ?? []),
                'gmv' => (int) ($snapshot['sales'] ?? 0),
            ];
        }

        $caps = $this->limits->caps($channelId);
        $used = [
            'users' => $this->users->countInChannel($channelId),
            'warehouses' => Tenant::as($channelId, fn (): int => Warehouse::query()->count()),
            'reps' => $this->reps->countInChannel($channelId),
            'skus' => $this->catalog->countInChannel($channelId),
        ];

        $limitUsage = [];
        foreach ($used as $key => $count) {
            $limitUsage[$key] = ['used' => $count, 'limit' => $caps[$key]];
        }

        $failedJobs = Tenant::as($channelId, fn (): int => ChannelProvisionJob::query()
            ->where('status', 'failed')
            ->count());

        return [
            'range' => $days.'d',
            'series' => $series,
            'limit_usage' => $limitUsage,
            'failed_jobs' => $failedJobs,
            // The only sync this module can see is provisioning. The Sync module is L3
            // and proposed; when it lands, its health belongs here in place of this.
            'sync_status' => $failedJobs === 0 ? 'healthy' : 'degraded',
        ];
    }

    private function days(string $range): int
    {
        return match ($range) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
    }
}
