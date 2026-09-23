<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A retailer's shop, looked up by retailer profile id.
 *
 * Identity owns `retailer_profiles`. Ordering prints a shop name on assignments, cart
 * sections, sub-orders and schedules, and needs to reject an unknown retailer — all
 * reads of an Identity table keyed by `retailer_id`.
 *
 * Not to be confused with {@see RetailerShoppingContext}, which is keyed by the
 * authenticated user object and throws for anyone who is not a retailer. These callers
 * hold an id and must tolerate absence.
 *
 * The shape carries only what Ordering reads today. `retailer_profiles` also holds
 * activity type, governorate, zone, coordinates and status; exposing any of them here
 * without a caller would be a leak, not a convenience.
 *
 * @phpstan-type RetailerShop array{id: int, shop_name: string, address: string|null}
 */
interface RetailerDirectory
{
    /**
     * @return RetailerShop|null
     */
    public function find(int $retailerId): ?array;

    /**
     * For callers that only reject an unknown retailer and read no field.
     */
    public function exists(int $retailerId): bool;

    /**
     * The phone of the app user who owns this shop.
     *
     * Separate from find() on purpose: it crosses into `app_users`, and most callers
     * want only the shop name — two of them inside a per-row loop.
     */
    public function phone(int $retailerId): ?string;

    /**
     * The app user who owns this shop — Notification writes the inbox by user id,
     * not retailer profile id.
     */
    public function appUserId(int $retailerId): ?int;

    /**
     * The shop id for an authenticated retailer. Loyalty credits the profile, not
     * the user row; Identity is the only module that can join the two.
     */
    public function profileIdForUser(int $appUserId): ?int;

    /**
     * The delivery zone this shop sits in, for repricing a rep's cart.
     *
     * Also separate from find(): only the two cart writers need it, and the shape stays
     * the two display fields the readers actually read.
     */
    public function zoneId(int $retailerId): ?int;

    /**
     * The shop's activity type — Pricing/Promotion target offers by it.
     */
    public function activityTypeId(int $retailerId): ?int;

    /**
     * Retailer group membership ids for price lists and offer targeting.
     *
     * @return list<int>
     */
    public function groupIds(int $retailerId): array;

    /**
     * How many retailer shops sit in a zone.
     *
     * For the impact count EP-AD-034 shows before a zone is disabled. It returns a number
     * and never a model or a row: the caller renders a confirmation dialog and has no
     * business reading `retailer_profiles`. Counting here rather than returning a list
     * for the caller to measure is what keeps rule 3 intact.
     */
    public function countInZone(int $zoneId): int;

    /**
     * Retailer profiles registered under an activity type — the `affected.retailers`
     * count EP-AD-043B shows before an activity type is disabled (BE-R04).
     */
    public function countByActivityType(int $activityTypeId): int;

    /**
     * Retailer profiles registered in a governorate — `affected.retailers` on
     * EP-AD-043A before a governorate is disabled (BE-R02).
     */
    public function countInGovernorate(int $governorateId): int;

    /**
     * Retailer profiles that declared a piece of equipment — `affected.retailers` on
     * EP-AD-043E (BE-R07). Counted through the `retailer_profile_equipments` pivot.
     */
    public function countByEquipment(int $equipmentId): int;

    /**
     * Retailer profiles that chose a root category at registration — part of the
     * in-use check behind EP-AD-043C (BE-R05). Counted through
     * `retailer_profile_categories`.
     */
    public function countByRootCategory(int $rootCategoryId): int;
}
