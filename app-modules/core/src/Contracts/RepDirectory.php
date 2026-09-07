<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RepDirectory
{
    public function exists(int $repUserId): bool;

    public function belongsToChannel(int $repUserId, int $channelId): bool;

    public function profileId(int $repUserId): ?int;

    public function userIdForProfile(int $profileId): ?int;

    /**
     * @return list<int>
     */
    public function zoneIdsForUser(int $repUserId): array;

    public function channelIdForUser(int $repUserId): ?int;

    public function displayName(int $repUserId): ?string;

    public function phone(int $repUserId): ?string;

    /**
     * How many reps serve a zone.
     *
     * For the impact count EP-AD-034 shows before a zone is disabled. A rep serves many
     * zones — the link is the `rep_profile_zones` pivot Identity owns, the same one
     * {@see self::zoneIdsForUser()} reads — so this counts distinct rep profiles with a
     * row for the zone, not rows in the pivot.
     *
     * Returns a number, never a model.
     */
    public function countInZone(int $zoneId): int;
}
