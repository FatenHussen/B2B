<?php

declare(strict_types=1);

namespace Modules\Inventory\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Inventory\Domain\Enums\MovementType;
use Modules\Inventory\Domain\Enums\TransferStatus;
use Modules\Inventory\Domain\Models\StockBalance;
use Modules\Inventory\Domain\Models\StockLot;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Inventory\Domain\Models\StockReservation;
use Modules\Inventory\Domain\Models\StockTransfer;

final class EloquentStockLedger implements StockLedger
{
    public function __construct(private readonly WarehouseDirectory $warehouses) {}

    public function snapshot(int $warehouseId, int $productId, ?int $variantId = null): array
    {
        $row = $this->balance($warehouseId, $productId, $variantId);

        return [
            'on_hand' => (int) $row->on_hand,
            'reserved' => (int) $row->reserved,
            'in_transit' => (int) $row->in_transit,
            'damaged' => (int) $row->damaged,
            'available' => $row->available(),
        ];
    }

    public function available(int $warehouseId, int $productId, ?int $variantId = null): int
    {
        return $this->snapshot($warehouseId, $productId, $variantId)['available'];
    }

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
    ): array {
        $bucket = $this->classifyBucket($bucket, $reason);

        return DB::transaction(function () use ($warehouseId, $productId, $variantId, $qtyDelta, $reason, $actor, $bucket, $refType, $refId): array {
            $row = $this->lock($warehouseId, $productId, $variantId);
            $before = (int) $row->{$bucket};
            $after = $before + $qtyDelta;
            if ($after < 0) {
                throw new DomainException(__('inventory.insufficient_stock'), 'insufficient_stock', 409);
            }
            $row->{$bucket} = $after;
            $row->save();

            $movement = $this->write(
                $row,
                MovementType::Adjust,
                $qtyDelta,
                $before,
                $after,
                $reason,
                $actor,
                $refType,
                $refId,
            );

            return ['movement_id' => (int) $movement->id, 'available' => $row->available()];
        });
    }

    public function reserve(
        int $subOrderId,
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qty,
        object $actor,
    ): void {
        DB::transaction(function () use ($subOrderId, $warehouseId, $productId, $variantId, $qty, $actor): void {
            $row = $this->lock($warehouseId, $productId, $variantId);
            if ($row->available() < $qty) {
                throw new DomainException(__('inventory.insufficient_stock'), 'insufficient_stock', 409);
            }
            $before = (int) $row->reserved;
            $row->reserved = $before + $qty;
            $row->save();

            StockReservation::query()->create([
                'warehouse_id' => $warehouseId,
                'sub_order_id' => $subOrderId,
                'product_id' => $productId,
                'variant_id' => $this->vid($variantId),
                'qty' => $qty,
            ]);

            $this->write($row, MovementType::Reserve, $qty, $before, (int) $row->reserved, 'confirm', $actor, 'sub_order', $subOrderId);
        });
    }

    public function release(int $subOrderId, object $actor): void
    {
        DB::transaction(function () use ($subOrderId, $actor): void {
            $rows = Tenant::withoutScope(fn () => StockReservation::query()
                ->where('sub_order_id', $subOrderId)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->get());

            foreach ($rows as $reservation) {
                $this->inWarehouse((int) $reservation->warehouse_id, function () use ($reservation, $actor, $subOrderId): void {
                    $row = $this->lock(
                        (int) $reservation->warehouse_id,
                        (int) $reservation->product_id,
                        (int) $reservation->variant_id ?: null,
                    );
                    $qty = (int) $reservation->qty;
                    $before = (int) $row->reserved;
                    $row->reserved = max(0, $before - $qty);
                    $row->save();
                    $reservation->released_at = now();
                    $reservation->save();
                    $this->write($row, MovementType::Release, -$qty, $before, (int) $row->reserved, 'release', $actor, 'sub_order', $subOrderId);
                });
            }
        });
    }

    public function pickDeduct(int $subOrderId, object $actor): void
    {
        DB::transaction(function () use ($subOrderId, $actor): void {
            $rows = Tenant::withoutScope(fn () => StockReservation::query()
                ->where('sub_order_id', $subOrderId)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->get());

            foreach ($rows as $reservation) {
                $this->inWarehouse((int) $reservation->warehouse_id, function () use ($reservation, $actor, $subOrderId): void {
                    $row = $this->lock(
                        (int) $reservation->warehouse_id,
                        (int) $reservation->product_id,
                        (int) $reservation->variant_id ?: null,
                    );
                    $qty = (int) $reservation->qty;
                    $onHandBefore = (int) $row->on_hand;
                    $reservedBefore = (int) $row->reserved;
                    if ($onHandBefore < $qty) {
                        throw new DomainException(__('inventory.insufficient_stock'), 'insufficient_stock', 409);
                    }
                    $row->on_hand = $onHandBefore - $qty;
                    $row->reserved = max(0, $reservedBefore - $qty);
                    $row->save();
                    $reservation->released_at = now();
                    $reservation->save();
                    $this->write($row, MovementType::PickDeduct, -$qty, $onHandBefore, (int) $row->on_hand, 'handover', $actor, 'sub_order', $subOrderId);
                    $this->deductLots(
                        (int) $reservation->warehouse_id,
                        (int) $reservation->product_id,
                        (int) $reservation->variant_id ?: null,
                        $qty,
                    );
                });
            }
        });
    }

    public function receive(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qty,
        object $actor,
        string $refType,
        int $refId,
    ): void {
        DB::transaction(function () use ($warehouseId, $productId, $variantId, $qty, $actor, $refType, $refId): void {
            $row = $this->lock($warehouseId, $productId, $variantId);
            $inTransit = (int) $row->in_transit;
            if ($inTransit > 0) {
                $consumed = min($inTransit, $qty);
                $row->in_transit = $inTransit - $consumed;
            }
            $before = (int) $row->on_hand;
            $row->on_hand = $before + $qty;
            $row->save();
            $this->write($row, MovementType::Receive, $qty, $before, (int) $row->on_hand, 'receive', $actor, $refType, $refId);
        });
    }

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
    ): void {
        $this->receive($warehouseId, $productId, $variantId, $qty, $actor, $refType, $refId);

        if (($lotNo === null || $lotNo === '') && ($expiryDate === null || $expiryDate === '')) {
            return;
        }

        $this->inWarehouse($warehouseId, function () use ($warehouseId, $productId, $variantId, $qty, $lotNo, $expiryDate): void {
            StockLot::query()->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'variant_id' => $this->vid($variantId) ?: null,
                'lot_no' => $lotNo !== '' ? $lotNo : null,
                'expiry_date' => $expiryDate !== null && $expiryDate !== '' ? $expiryDate : null,
                'qty' => $qty,
            ]);
        });
    }

    public function earliestExpiry(int $warehouseId, int $productId, ?int $variantId = null): ?string
    {
        return $this->inWarehouse($warehouseId, function () use ($warehouseId, $productId, $variantId): ?string {
            $date = StockLot::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where(function ($q) use ($variantId): void {
                    $vid = $this->vid($variantId);
                    if ($vid > 0) {
                        $q->where('variant_id', $vid);
                    } else {
                        $q->where(fn ($inner) => $inner->whereNull('variant_id')->orWhere('variant_id', 0));
                    }
                })
                ->where('qty', '>', 0)
                ->whereNotNull('expiry_date')
                ->orderBy('expiry_date')
                ->value('expiry_date');

            if ($date === null) {
                return null;
            }

            return $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d')
                : (string) $date;
        });
    }

    public function listLots(int $warehouseId, ?int $productId, ?string $expiryBefore, int $page, int $perPage): array
    {
        return $this->inWarehouse($warehouseId, function () use ($warehouseId, $productId, $expiryBefore, $page, $perPage): array {
            $query = StockLot::query()
                ->where('warehouse_id', $warehouseId)
                ->orderByRaw('expiry_date is null')
                ->orderBy('expiry_date')
                ->orderBy('id');

            if ($productId !== null) {
                $query->where('product_id', $productId);
            }
            if ($expiryBefore !== null && $expiryBefore !== '') {
                $query->where('expiry_date', '<=', $expiryBefore);
            }

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => collect($paginator->items())->map(static function (StockLot $lot): array {
                    return [
                        'id' => (int) $lot->id,
                        'warehouse_id' => (int) $lot->warehouse_id,
                        'product_id' => (int) $lot->product_id,
                        'variant_id' => $lot->variant_id !== null && (int) $lot->variant_id > 0
                            ? (int) $lot->variant_id
                            : null,
                        'lot_no' => $lot->lot_no,
                        'expiry_date' => $lot->expiry_date?->format('Y-m-d'),
                        'qty' => (int) $lot->qty,
                    ];
                })->all(),
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ];
        });
    }

    public function adjustLot(int $lotId, int $warehouseId, int $qtyDelta, string $reason, object $actor): array
    {
        return DB::transaction(function () use ($lotId, $warehouseId, $qtyDelta, $reason, $actor): array {
            return $this->inWarehouse($warehouseId, function () use ($lotId, $warehouseId, $qtyDelta, $reason, $actor): array {
                $lot = StockLot::query()
                    ->whereKey($lotId)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                if ($lot === null) {
                    throw new DomainException(__('errors.not_found'), 'not_found', 404);
                }

                $newQty = (int) $lot->qty + $qtyDelta;
                if ($newQty < 0) {
                    throw new DomainException(__('inventory.insufficient_stock'), 'insufficient_stock', 409);
                }

                $lot->qty = $newQty;
                $lot->save();

                $result = $this->adjust(
                    $warehouseId,
                    (int) $lot->product_id,
                    $lot->variant_id !== null && (int) $lot->variant_id > 0 ? (int) $lot->variant_id : null,
                    $qtyDelta,
                    $reason,
                    $actor,
                    'on_hand',
                    'stock_lot',
                    (int) $lot->id,
                );

                return [
                    'id' => (int) $lot->id,
                    'qty' => (int) $lot->qty,
                    'movement_id' => $result['movement_id'],
                    'available' => $result['available'],
                ];
            });
        });
    }

    public function transferSent(int $fromWarehouseId, int $toWarehouseId, array $lines, int $transferId, object $actor): void
    {
        DB::transaction(function () use ($fromWarehouseId, $toWarehouseId, $lines, $transferId, $actor): void {
            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;
                $qty = (int) $line['qty'];

                $from = $this->lock($fromWarehouseId, $productId, $variantId);
                if ($from->available() < $qty) {
                    throw new DomainException(__('inventory.insufficient_stock'), 'insufficient_stock', 409);
                }
                $before = (int) $from->on_hand;
                $from->on_hand = $before - $qty;
                $from->save();
                $this->write($from, MovementType::TransferOut, -$qty, $before, (int) $from->on_hand, 'transfer', $actor, 'stock_transfer', $transferId);

                $to = $this->lock($toWarehouseId, $productId, $variantId);
                $transitBefore = (int) $to->in_transit;
                $to->in_transit = $transitBefore + $qty;
                $to->save();
                $this->write($to, MovementType::TransferIn, $qty, $transitBefore, (int) $to->in_transit, 'transfer', $actor, 'stock_transfer', $transferId);
            }
        });
    }

    public function transferReceived(int $transferId): void
    {
        $transfer = StockTransfer::query()->find($transferId);
        if ($transfer === null) {
            return;
        }
        if ($transfer->status === TransferStatus::Received) {
            return;
        }
        $transfer->forceFill(['status' => TransferStatus::Received])->save();
    }

    public function returnIn(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qty,
        string $condition,
        object $actor,
        string $refType,
        int $refId,
    ): void {
        DB::transaction(function () use ($warehouseId, $productId, $variantId, $qty, $condition, $actor, $refType, $refId): void {
            $this->inWarehouse($warehouseId, function () use ($warehouseId, $productId, $variantId, $qty, $condition, $actor, $refType, $refId): void {
                $row = $this->lock($warehouseId, $productId, $variantId);
                $bucket = $condition === 'resalable' ? 'on_hand' : 'damaged';
                $before = (int) $row->{$bucket};
                $row->{$bucket} = $before + $qty;
                $row->save();
                $this->write($row, MovementType::ReturnIn, $qty, $before, (int) $row->{$bucket}, $condition, $actor, $refType, $refId);
            });
        });
    }

    public function stocktakeDelta(
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qtyDelta,
        object $actor,
        int $stocktakeId,
    ): void {
        if ($qtyDelta === 0) {
            return;
        }
        DB::transaction(function () use ($warehouseId, $productId, $variantId, $qtyDelta, $actor, $stocktakeId): void {
            $row = $this->lock($warehouseId, $productId, $variantId);
            $before = (int) $row->on_hand;
            $after = $before + $qtyDelta;
            if ($after < 0) {
                throw new DomainException(__('inventory.insufficient_stock'), 'insufficient_stock', 409);
            }
            $row->on_hand = $after;
            $row->save();
            $this->write($row, MovementType::Stocktake, $qtyDelta, $before, $after, 'stocktake', $actor, 'stocktake', $stocktakeId);
        });
    }

    public function restockUndelivered(int $subOrderId, object $actor): void
    {
        $this->receiveFromField($subOrderId, $actor);
    }

    private function receiveFromField(int $subOrderId, object $actor): void
    {
        $this->returnInFromReservation($subOrderId, $actor);
    }

    private function returnInFromReservation(int $subOrderId, object $actor): void
    {
        DB::transaction(function () use ($subOrderId, $actor): void {
            $rows = StockReservation::query()
                ->where('sub_order_id', $subOrderId)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $reservation) {
                $row = $this->lock(
                    (int) $reservation->warehouse_id,
                    (int) $reservation->product_id,
                    (int) $reservation->variant_id ?: null,
                );
                $qty = (int) $reservation->qty;
                $before = (int) $row->on_hand;
                $row->on_hand = $before + $qty;
                $row->save();
                $this->write($row, MovementType::Receive, $qty, $before, (int) $row->on_hand, 'return_trip', $actor, 'sub_order', $subOrderId);
            }
        });
    }

    private function deductLots(int $warehouseId, int $productId, ?int $variantId, int $qty): void
    {
        $remaining = $qty;
        $lots = StockLot::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where(function ($q) use ($variantId): void {
                $vid = $this->vid($variantId);
                if ($vid > 0) {
                    $q->where('variant_id', $vid);
                } else {
                    $q->where(fn ($inner) => $inner->whereNull('variant_id')->orWhere('variant_id', 0));
                }
            })
            ->where('qty', '>', 0)
            ->orderByRaw('expiry_date is null')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((int) $lot->qty, $remaining);
            $lot->qty = (int) $lot->qty - $take;
            $lot->save();
            $remaining -= $take;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inWarehouse(int $warehouseId, callable $callback): mixed
    {
        return Tenant::as($this->warehouses->channelId($warehouseId), $callback);
    }

    private function classifyBucket(string $bucket, string $reason): string
    {
        if ($bucket === 'damaged' || str_contains(mb_strtolower($reason), 'تلف') || str_contains(mb_strtolower($reason), 'damag')) {
            return 'damaged';
        }

        return 'on_hand';
    }

    private function vid(?int $variantId): int
    {
        return $variantId !== null && $variantId > 0 ? $variantId : 0;
    }

    private function balance(int $warehouseId, int $productId, ?int $variantId): StockBalance
    {
        $row = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('variant_id', $this->vid($variantId))
            ->first();

        if ($row !== null) {
            return $row;
        }

        return StockBalance::query()->create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'variant_id' => $this->vid($variantId),
            'on_hand' => 0,
            'reserved' => 0,
            'in_transit' => 0,
            'damaged' => 0,
        ]);
    }

    private function lock(int $warehouseId, int $productId, ?int $variantId): StockBalance
    {
        $this->assertWarehouse($warehouseId);
        $this->balance($warehouseId, $productId, $variantId);

        return StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('variant_id', $this->vid($variantId))
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertWarehouse(int $warehouseId): void
    {
        $channelId = Tenant::currentId();
        if ($channelId !== null && ! $this->warehouses->belongsToChannel($warehouseId, $channelId)) {
            throw new DomainException(__('inventory.warehouse_not_found'), 'not_found', 404);
        }
    }

    private function write(
        StockBalance $row,
        MovementType $type,
        int $delta,
        int $before,
        int $after,
        string $reason,
        object $actor,
        ?string $refType,
        ?int $refId,
    ): StockMovement {
        $actorId = method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null;

        return StockMovement::query()->create([
            'warehouse_id' => $row->warehouse_id,
            'product_id' => $row->product_id,
            'variant_id' => $row->variant_id,
            'type' => $type,
            'qty_delta' => $delta,
            'qty_before' => $before,
            'qty_after' => $after,
            'reason' => $reason,
            'actor_type' => $actor::class,
            'actor_id' => $actorId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'at' => now(),
        ]);
    }
}
