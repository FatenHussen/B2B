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
     *     name: string,
     *     brand: string|null
     * }>
     */
    public function lines(int $subOrderId): array;

    public function transition(int $subOrderId, string $to, object $actor, ?string $stage = null): void;

    public function status(int $subOrderId): ?string;

    public function replaceLineQty(int $subOrderId, int $lineId, int $qty): int;

    /**
     * @param  list<string>  $statuses
     * @return list<int>
     */
    public function idsForRep(int $repUserId, array $statuses): array;

    /**
     * Every sub-order id that belongs to a retailer, across channels — the owner side of
     * an app-facing list that must filter in its query rather than after it (BE-C12).
     *
     * @return list<int>
     */
    public function idsForRetailer(int $retailerId): array;
}
