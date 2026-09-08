<?php

declare(strict_types=1);

namespace Modules\Tenancy\Infrastructure;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelZoneLookup;
use Modules\Tenancy\Domain\Models\SupplyChannel;

final class EloquentChannelDirectory implements ChannelDirectory
{
    public function exists(int $channelId): bool
    {
        return SupplyChannel::query()->whereKey($channelId)->exists();
    }

    public function isActive(int $channelId): bool
    {
        return SupplyChannel::query()
            ->whereKey($channelId)
            ->where('status', ChannelStatus::Active)
            ->exists();
    }

    public function coversZone(int $channelId, int $zoneId): bool
    {
        return ChannelZoneLookup::query()
            ->where('supply_channel_id', $channelId)
            ->where('zone_id', $zoneId)
            ->exists();
    }

    public function coversAllZones(int $channelId, array $zoneIds): bool
    {
        if ($zoneIds === []) {
            return false;
        }

        $covered = ChannelZoneLookup::query()
            ->where('supply_channel_id', $channelId)
            ->whereIn('zone_id', $zoneIds)
            ->pluck('zone_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return count($covered) === count(array_unique($zoneIds));
    }

    public function name(int $channelId): ?string
    {
        $name = SupplyChannel::query()->whereKey($channelId)->value('name');

        return is_string($name) ? $name : null;
    }

    public function activeIdsCoveringZone(int $zoneId): array
    {
        $channelIds = ChannelZoneLookup::query()
            ->where('zone_id', $zoneId)
            ->pluck('supply_channel_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if ($channelIds === []) {
            return [];
        }

        return SupplyChannel::query()
            ->whereIn('id', $channelIds)
            ->where('status', ChannelStatus::Active)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
