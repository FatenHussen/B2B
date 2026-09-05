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
     * @param  list<array{product_id: int, variant_id: int|null, qty: int}>  $lines
     */
    public function transferSent(int $fromWarehouseId, int $toWarehouseId, array $lines, int $transferId, object $actor): void;

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
