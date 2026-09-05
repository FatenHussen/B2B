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
     * The delivery zone this shop sits in, for repricing a rep's cart.
     *
     * Also separate from find(): only the two cart writers need it, and the shape stays
     * the two display fields the readers actually read.
     */
    public function zoneId(int $retailerId): ?int;
}
