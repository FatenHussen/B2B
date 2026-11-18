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
     * Channel settings JSON blob (quiet hours, feature flags, …). Empty array when unset.
     *
     * @return array<string, mixed>
     */
    public function settings(int $channelId): array;

    /**
     * Zone ids this channel covers (from channel_zone).
     *
     * @return list<int>
     */
    public function zoneIds(int $channelId): array;

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

    /**
     * Channels that declared an activity type in their profile — `affected.channels`
     * before an activity type is disabled, EP-AD-043B (BE-R04).
     */
    public function countByActivityType(int $activityTypeId): int;

    /**
     * Channels whose coverage names a governorate — `affected.channels` before a
     * governorate is disabled, EP-AD-043A (BE-R02).
     */
    public function countCoveringGovernorate(int $governorateId): int;

    /**
     * Discovery cards for the retailer home `channels_slider` (EP-RT-010).
     * `brand_count` is left at 0 — Catalog fills it from its own brands table.
     *
     * @param  list<int>  $channelIds
     * @return list<array{
     *     id: int,
     *     name: string,
     *     logo: string|null,
     *     description: string|null,
     *     is_verified: bool,
     *     rating: float,
     *     brand_count: int,
     *     delivery_label: string
     * }>
     */
    public function cardsForIds(array $channelIds): array;
}
