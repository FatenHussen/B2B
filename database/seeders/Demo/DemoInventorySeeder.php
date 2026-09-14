<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Inventory\Domain\Enums\MovementType;
use Modules\Inventory\Domain\Enums\TransferStatus;
use Modules\Inventory\Domain\Models\StockBalance;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Inventory\Domain\Models\StockReorderPoint;
use Modules\Inventory\Domain\Models\StockReservation;
use Modules\Inventory\Domain\Models\StockTransfer;
use Modules\Inventory\Domain\Models\StockTransferLine;
use Modules\Inventory\Domain\Models\WarehouseLocation;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderLine;

/**
 * Stock in both warehouses, its movement history, reorder points and transfers.
 *
 * Runs after the ordering seeder on purpose: `reserved` on each balance is the sum of the
 * open sub-orders' lines, and a reservation row is written per line, so the levels screen
 * and the order queue tell the same story.
 */
final class DemoInventorySeeder extends DemoSeeder
{
    /**
     * Main-warehouse stock per SKU: `[on_hand, damaged]`. Variants of `SHP` are seeded per
     * variant below instead. Draft and disabled products get a small quantity so the
     * levels screen still finds them by product id.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const MAIN_STOCK = [
        'SUG-1KG' => [1_200, 15],
        'RICE-5KG' => [180, 2],
        'OIL-1L' => [720, 6],
        'OIL-4L' => [96, 0],
        'TOM-400' => [480, 12],
        'TUNA-160' => [600, 0],
        'PASTA-500' => [840, 0],
        'TEA-500' => [240, 0],
        'NESC-200' => [144, 0],
        'COLA-330' => [1_440, 24],
        'PEPSI-1500' => [360, 0],
        'JUICE-ORG-1L' => [288, 4],
        'WATER-500' => [150, 0],
        'CHZ-500' => [96, 3],
        'YOG-1KG' => [72, 0],
        'LAB-500' => [84, 0],
        'MILK-1L' => [432, 12],
        'DET-3KG' => [64, 0],
        'FLC-1L' => [120, 0],
        'TP-100' => [240, 0],
        'CHOC-40' => [960, 0],
        'BIS-100' => [1_200, 30],
        'CHIPS-50' => [1_440, 0],
        'JUICE-MNG-1L' => [48, 0],
        'SNK-NEW' => [24, 0],
        'OLD-COLA-250' => [12, 12],
    ];

    /** @var array<string, int> */
    private const MAIN_VARIANT_STOCK = ['SHP-200مل' => 90, 'SHP-400مل' => 60];

    /** @var array<string, array{0: int, 1: int}> */
    private const ALEPPO_STOCK = [
        'SUG-1KG' => [300, 0],
        'RICE-5KG' => [40, 0],
        'OIL-1L' => [144, 2],
        'TEA-500' => [48, 0],
        'COLA-330' => [240, 0],
        'PASTA-500' => [120, 0],
        'MILK-1L' => [96, 0],
        'CHIPS-50' => [240, 0],
    ];

    /** @var array<string, int> */
    private const REORDER_POINTS = [
        'SUG-1KG' => 300,
        'RICE-5KG' => 40,
        'OIL-1L' => 150,
        'COLA-330' => 480,
        'MILK-1L' => 120,
        'CHZ-500' => 30,
        'DET-3KG' => 20,
        'NESC-200' => 36,
    ];

    /**
     * Transfers from the main warehouse to Aleppo: one still on the road (its lines are
     * the Aleppo `in_transit` figures) and one already received.
     *
     * @var list<array{status: string, days_ago: int, lines: array<string, int>}>
     */
    private const TRANSFERS = [
        ['status' => 'sent', 'days_ago' => 1, 'lines' => ['SUG-1KG' => 100, 'OIL-1L' => 48, 'COLA-330' => 96]],
        ['status' => 'received', 'days_ago' => 10, 'lines' => ['RICE-5KG' => 20, 'TEA-500' => 24]],
    ];

    /** Sub-order states whose lines still hold a reservation against on-hand stock. */
    private const RESERVING_STATUSES = ['confirmed', 'assigned', 'accepted', 'postponed'];

    public function run(): void
    {
        $this->seedLocations();
        $this->seedBalances();
        $this->seedReorderPoints();
        $this->seedTransfers();
        $this->seedReservations();
    }

    private function seedLocations(): void
    {
        $layout = [
            self::WAREHOUSE_MAIN => [['A', '01'], ['A', '02'], ['A', '03'], ['B', '01'], ['B', '02'], ['C', '01']],
            self::WAREHOUSE_ALEPPO => [['A', '01'], ['A', '02']],
        ];

        foreach ($layout as $warehouse => $slots) {
            $warehouseId = $this->warehouseId($warehouse);

            foreach ($slots as [$aisle, $shelf]) {
                WarehouseLocation::query()->firstOrCreate(
                    ['warehouse_id' => $warehouseId, 'code' => $aisle.'-'.$shelf],
                    ['aisle' => $aisle, 'shelf' => $shelf],
                );
            }
        }
    }

    private function seedBalances(): void
    {
        $main = $this->warehouseId(self::WAREHOUSE_MAIN);
        $aleppo = $this->warehouseId(self::WAREHOUSE_ALEPPO);
        $inTransit = self::TRANSFERS[0]['lines'];

        foreach (self::MAIN_STOCK as $sku => [$onHand, $damaged]) {
            $this->balance($main, $this->productId($sku), 0, $onHand, $damaged, 0);
        }

        foreach (self::MAIN_VARIANT_STOCK as $variantSku => $onHand) {
            $variant = ProductVariant::query()->where('sku', $variantSku)->firstOrFail();
            $this->balance($main, (int) $variant->product_id, (int) $variant->id, $onHand, 0, 0);
        }

        foreach (self::ALEPPO_STOCK as $sku => [$onHand, $damaged]) {
            $this->balance($aleppo, $this->productId($sku), 0, $onHand, $damaged, $inTransit[$sku] ?? 0);
        }
    }

    /**
     * Writes the balance and, the first time only, the receipt that put the stock there and
     * an adjustment for whatever was found damaged — so every unit on the levels screen has
     * a line in the movements screen explaining it.
     */
    private function balance(int $warehouseId, int $productId, int $variantId, int $onHand, int $damaged, int $inTransit): void
    {
        $balance = StockBalance::query()->firstOrCreate(
            ['warehouse_id' => $warehouseId, 'product_id' => $productId, 'variant_id' => $variantId],
            ['on_hand' => $onHand, 'reserved' => 0, 'in_transit' => $inTransit, 'damaged' => $damaged],
        );

        if (! $balance->wasRecentlyCreated) {
            return;
        }

        $received = $onHand + $damaged;
        $receivedAt = Carbon::now('Asia/Damascus')->subDays(20)->setTime(9, 0);

        $this->movement($balance, MovementType::Receive, $received, 0, $received, 'استلام بضاعة من المورد', 'goods_receipt', 1, $receivedAt);

        if ($damaged > 0) {
            $this->movement($balance, MovementType::Adjust, -$damaged, $received, $onHand, 'تالف عند الفحص', null, null, $receivedAt->copy()->addHours(2));
        }
    }

    private function seedReorderPoints(): void
    {
        $main = $this->warehouseId(self::WAREHOUSE_MAIN);

        foreach (self::REORDER_POINTS as $sku => $point) {
            StockReorderPoint::query()->updateOrCreate(
                ['warehouse_id' => $main, 'product_id' => $this->productId($sku), 'variant_id' => 0],
                ['point' => $point],
            );
        }
    }

    /**
     * Transfers have no natural key, so they are written once: a channel that already has
     * any keeps what it has.
     */
    private function seedTransfers(): void
    {
        if (StockTransfer::query()->exists()) {
            return;
        }

        $main = $this->warehouseId(self::WAREHOUSE_MAIN);
        $aleppo = $this->warehouseId(self::WAREHOUSE_ALEPPO);

        foreach (self::TRANSFERS as $row) {
            $at = Carbon::now('Asia/Damascus')->subDays($row['days_ago'])->setTime(11, 0);

            $transfer = StockTransfer::query()->create([
                'from_warehouse_id' => $main,
                'to_warehouse_id' => $aleppo,
                'status' => TransferStatus::from($row['status']),
            ]);
            $transfer->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

            foreach ($row['lines'] as $sku => $qty) {
                $productId = $this->productId($sku);

                StockTransferLine::query()->create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'variant_id' => 0,
                    'qty' => $qty,
                ]);

                // The movement pair `transferSent` would have written. Balances were
                // seeded net of these, so only the ledger lines are added here.
                $from = StockBalance::query()->where('warehouse_id', $main)->where('product_id', $productId)->where('variant_id', 0)->first();
                $to = StockBalance::query()->where('warehouse_id', $aleppo)->where('product_id', $productId)->where('variant_id', 0)->first();

                if ($from !== null) {
                    $this->movement($from, MovementType::TransferOut, -$qty, (int) $from->on_hand + $qty, (int) $from->on_hand, 'transfer', 'stock_transfer', (int) $transfer->id, $at);
                }
                if ($to !== null) {
                    $before = $row['status'] === 'sent' ? (int) $to->in_transit - $qty : 0;
                    $this->movement($to, MovementType::TransferIn, $qty, max(0, $before), $before + $qty, 'transfer', 'stock_transfer', (int) $transfer->id, $at);
                }
            }
        }
    }

    /**
     * Reservations mirror `StockLedger::reserve`: one row per line of every sub-order that
     * is confirmed but not yet picked, and the main warehouse's `reserved` is their sum.
     */
    private function seedReservations(): void
    {
        $main = $this->warehouseId(self::WAREHOUSE_MAIN);

        $open = SubOrder::query()
            ->whereIn('status', array_map(fn (string $s) => SubOrderStatus::from($s), self::RESERVING_STATUSES))
            ->pluck('id');

        $reservedByKey = [];

        foreach (SubOrderLine::query()->whereIn('sub_order_id', $open)->get() as $line) {
            $variantId = (int) ($line->variant_id ?? 0);

            StockReservation::query()->firstOrCreate(
                ['sub_order_id' => $line->sub_order_id, 'product_id' => $line->product_id, 'variant_id' => $variantId, 'warehouse_id' => $main],
                ['qty' => $line->qty, 'released_at' => null],
            );

            $key = $line->product_id.':'.$variantId;
            $reservedByKey[$key] = ($reservedByKey[$key] ?? 0) + (int) $line->qty;
        }

        foreach ($reservedByKey as $key => $qty) {
            [$productId, $variantId] = array_map('intval', explode(':', $key));

            StockBalance::query()
                ->where('warehouse_id', $main)
                ->where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->update(['reserved' => $qty]);
        }
    }

    private function movement(StockBalance $balance, MovementType $type, int $delta, int $before, int $after, string $reason, ?string $refType, ?int $refId, Carbon $at): void
    {
        $managerId = ChannelUser::query()->where('phone', '+963900000001')->value('id');

        StockMovement::query()->create([
            'warehouse_id' => $balance->warehouse_id,
            'product_id' => $balance->product_id,
            'variant_id' => $balance->variant_id,
            'type' => $type,
            'qty_delta' => $delta,
            'qty_before' => $before,
            'qty_after' => $after,
            'reason' => $reason,
            'actor_type' => $managerId !== null ? ChannelUser::class : null,
            'actor_id' => $managerId !== null ? (int) $managerId : null,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'at' => $at,
        ]);
    }
}
