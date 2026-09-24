<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface StockLedger
{
    /**
     * @return array{on_hand: int, reserved: int, in_transit: int, damaged: int, available: int}
     */
    public function snapshot(int $warehouseId, int $productId, ?int $variantId = null): array;

    public function available(int $warehouseId, int $productId, ?int $variantId = null): int;

    /**
     * @return array{movement_id: int, available: int}
     */
    public function adjust(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qtyDelta,
        string $reason,
        object $actor,
        string $bucket = 'on_hand',
        ?string $refType = null,
        ?int $refId = null,
    ): array;

    public function reserve(
        int $subOrderId,
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qty,
        object $actor,
    ): void;

    public function release(int $subOrderId, object $actor): void;

    public function pickDeduct(int $subOrderId, object $actor): void;

    public function receive(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qty,
        object $actor,
        string $refType,
        int $refId,
    ): void;

    /**
     * Receive into on_hand and record a FEFO lot when lot_no/expiry are present.
     */
    public function receiveLot(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qty,
        object $actor,
        string $refType,
        int $refId,
        ?string $lotNo,
        ?string $expiryDate,
    ): void;

    /**
     * Earliest non-empty lot expiry (Y-m-d) for the SKU, or null.
     */
    public function earliestExpiry(int $warehouseId, int $productId, ?int $variantId = null): ?string;

    /**
     * @return array{data: list<array{id:int,warehouse_id:int,product_id:int,variant_id:int|null,lot_no:string|null,expiry_date:string|null,qty:int}>, meta: array{page:int,per_page:int,total:int,last_page:int}}
     */
    public function listLots(int $warehouseId, ?int $productId, ?string $expiryBefore, int $page, int $perPage): array;

    /**
     * @return array{id:int, qty:int, movement_id:int, available:int}
     */
    public function adjustLot(int $lotId, int $warehouseId, int $qtyDelta, string $reason, object $actor): array;

    /**
     * @param  list<array{product_id: int, variant_id: int|null, qty: int}>  $lines
     */
    public function transferSent(int $fromWarehouseId, int $toWarehouseId, array $lines, int $transferId, object $actor): void;

    public function transferReceived(int $transferId): void;

    public function returnIn(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qty,
        string $condition,
        object $actor,
        string $refType,
        int $refId,
    ): void;

    public function stocktakeDelta(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qtyDelta,
        object $actor,
        int $stocktakeId,
    ): void;

    public function restockUndelivered(int $subOrderId, object $actor): void;
}
