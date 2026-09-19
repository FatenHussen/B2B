<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RepDirectory
{
    public function exists(int $repUserId): bool;

    public function belongsToChannel(int $repUserId, int $channelId): bool;

    /**
     * Does this rep belong to the channel AND hold an `active` profile there?
     *
     * The question to ask before giving a rep work. {@see self::belongsToChannel()} is
     * membership regardless of status — right for reading a disabled rep's wallet or
     * settling it, wrong for assigning them a sub-order.
     */
    public function isActiveInChannel(int $repUserId, int $channelId): bool;

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

    /**
     * Rep profiles a channel carries against its `reps` plan limit (BE-T12): every
     * profile on the channel that is not rejected or disabled. Pending profiles count —
     * a limit that only bit after approval would let a channel queue an unbounded
     * backlog and then approve past the cap.
     */
    public function countInChannel(int $channelId): int;

    /**
     * Rep profiles registered under an activity type — `affected.reps` before an
     * activity type is disabled (BE-R04).
     */
    public function countByActivityType(int $activityTypeId): int;
}
