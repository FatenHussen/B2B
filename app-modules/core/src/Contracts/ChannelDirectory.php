<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ChannelDirectory
{
    public function exists(int $channelId): bool;

    public function isActive(int $channelId): bool;

    public function coversZone(int $channelId, int $zoneId): bool;

    /**
     * @param  list<int>  $zoneIds
     */
    public function coversAllZones(int $channelId, array $zoneIds): bool;

    public function name(int $channelId): ?string;

    /**
     * Active channels whose coverage includes the zone.
     *
     * @return list<int>
     */
    public function activeIdsCoveringZone(int $zoneId): array;

    /**
     * Every supply channel id (any status). Used to fan out nightly snapshots.
     *
     * @return list<int>
     */
    public function allIds(): array;
}
