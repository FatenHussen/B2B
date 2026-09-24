<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Channel-owned retailer groups — Identity owns the rows; Pricing/Promotion only
 * read membership ids and verify group existence by id.
 */
interface RetailerGroupDirectory
{
    /**
     * @return list<int>
     */
    public function groupIdsForRetailer(int $retailerId): array;

    /**
     * @param  list<int>  $groupIds
     */
    public function allExistInChannel(int $channelId, array $groupIds): bool;

    public function existsInChannel(int $channelId, int $groupId): bool;

    /**
     * True when any price-list / offer still names this group — callers map to 422 ref_in_use.
     */
    public function isInUse(int $groupId): bool;

    /**
     * App users whose shops sit in any of the given retailer groups — notification targeting.
     *
     * @param  list<int>  $groupIds
     * @return list<int>
     */
    public function appUserIdsInGroups(array $groupIds): array;
}
