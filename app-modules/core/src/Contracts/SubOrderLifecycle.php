<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface SubOrderLifecycle
{
    /**
     * @return array{
     *     id: int,
     *     sub_order_no: string,
     *     status: string,
     *     channel_id: int,
     *     retailer_id: int,
     *     zone_id: int|null,
     *     rep_id: int|null,
     *     rep_user_id: int|null,
     *     total: int,
     *     created_at: string|null,
     *     updated_at: string|null,
     *     scheduled_at: string|null,
     *     shop_name: string,
     *     zone_name: string,
     *     channel_name: string,
     *     address: string|null,
     *     phone: string|null
     * }|null
     */
    public function header(int $subOrderId): ?array;

    /**
     * @return list<array{
     *     id: int,
     *     product_id: int,
     *     variant_id: int|null,
     *     qty: int,
     *     unit_price: int,
     *     discount: int,
     *     line_total: int,
     *     offer_id: int|null,
     *     name: string,
     *     brand: string|null
     * }>
     */
    public function lines(int $subOrderId): array;

    public function transition(int $subOrderId, string $to, object $actor, ?string $stage = null): void;

    public function postponeTo(int $subOrderId, object $actor, string $scheduledAt, string $reason): void;

    public function markUndelivered(int $subOrderId, object $actor, string $reason): void;

    public function status(int $subOrderId): ?string;

    public function replaceLineQty(int $subOrderId, int $lineId, int $qty): int;

    /**
     * @param  list<string>  $statuses
     * @return list<int>
     */
    public function idsForRep(int $repUserId, array $statuses): array;

    /**
     * How many of this rep's sub-orders sit in `$statuses`. A number, never a row —
     * Identity's morning home (EP-RP-002) must not load Ordering models.
     *
     * @param  list<string>  $statuses
     */
    public function countForRep(int $repUserId, array $statuses): int;

    /**
     * Sub-orders this rep submitted from the app today (Damascus calendar). Source
     * `rep_app` plus the pending event they wrote — not assignments the channel handed them.
     */
    public function countRegisteredToday(int $repUserId): int;

    /**
     * Every sub-order id that belongs to a retailer, across channels — the owner side of
     * an app-facing list that must filter in its query rather than after it (BE-C12).
     *
     * @return list<int>
     */
    public function idsForRetailer(int $retailerId): array;
}
