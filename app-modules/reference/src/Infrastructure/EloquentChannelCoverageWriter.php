<?php

declare(strict_types=1);

namespace Modules\Reference\Infrastructure;

use Modules\Core\Contracts\ChannelCoverageWriter;
use Modules\Core\Support\Tenant;
use Modules\Reference\Domain\Models\ChannelZone;

final class EloquentChannelCoverageWriter implements ChannelCoverageWriter
{
    public function replace(int $channelId, array $zoneIds): void
    {
        $zoneIds = array_values(array_unique(array_map('intval', $zoneIds)));

        Tenant::as($channelId, function () use ($zoneIds): void {
            ChannelZone::query()->whereNotIn('zone_id', $zoneIds === [] ? [0] : $zoneIds)->delete();

            foreach ($zoneIds as $zoneId) {
                ChannelZone::query()->firstOrCreate(
                    ['zone_id' => $zoneId],
                    ['delivery_fee' => 0],
                );
            }
        });
    }
}
