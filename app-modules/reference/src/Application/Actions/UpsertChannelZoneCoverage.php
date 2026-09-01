<?php

namespace Modules\Reference\Application\Actions;

use Modules\Reference\Domain\Models\ChannelZone;

class UpsertChannelZoneCoverage
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $zoneId, array $attributes): ChannelZone
    {
        /** @var ChannelZone $channelZone */
        $channelZone = ChannelZone::query()->updateOrCreate(
            ['zone_id' => $zoneId],
            $attributes,
        );

        return $channelZone;
    }
}
