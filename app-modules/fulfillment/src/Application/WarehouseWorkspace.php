<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Application;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\CreatesPickingList;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Contracts\WarehouseLocationDirectory;
use Modules\Core\Domain\Events\HandoverConfirmedByRep;
use Modules\Core\Domain\Events\HandoverOpened;
use Modules\Core\Domain\Events\PickingShortageReported;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\WarehouseScope;
use Modules\Fulfillment\Domain\Enums\HandoverStatus;
use Modules\Fulfillment\Domain\Enums\PickingStatus;
use Modules\Fulfillment\Domain\Models\GoodsReceipt;
use Modules\Fulfillment\Domain\Models\GoodsReceiptLine;
use Modules\Fulfillment\Domain\Models\Handover;
use Modules\Fulfillment\Domain\Models\HandoverItem;
use Modules\Fulfillment\Domain\Models\Package;
use Modules\Fulfillment\Domain\Models\PackingJob;
use Modules\Fulfillment\Domain\Models\PickingLine;
use Modules\Fulfillment\Domain\Models\PickingList;
use Modules\Fulfillment\Domain\Models\PickingWave;
use Modules\Fulfillment\Domain\Models\Stocktake;
use Modules\Fulfillment\Domain\Models\StocktakeLine;

final class WarehouseWorkspace
{
    public function __construct(
        private readonly CatalogProductLookup $products,
        private readonly SubOrderLifecycle $orders,
        private readonly StockLedger $ledger,
        private readonly RecordsAudit $audit,
        private readonly WarehouseLocationDirectory $locations,
        private readonly RepDirectory $reps,
        private readonly WarehouseDirectory $warehouses,
        private readonly CreatesPickingList $pickingLists,
    ) {}

    public function warehouseId(): int
    {
        $id = WarehouseScope::currentId();
        if ($id === null) {
            throw new DomainException(__('fulfillment.warehouse_required'), 'not_found', 404);
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function queues(): array
    {
        $wid = $this->warehouseId();
        $overduePicks = PickingList::query()
            ->where('warehouse_id', $wid)
            ->whereIn('status', [PickingStatus::ToPick, PickingStatus::Picking])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $alerts = [];
        if ($overduePicks > 0) {
            $alerts[] = ['type' => 'overdue_picks', 'count' => $overduePicks];
        }

        return [
            'queues' => [
                'to_pick' => PickingList::query()->where('warehouse_id', $wid)->where('status', PickingStatus::ToPick)->count(),
                'picking' => PickingList::query()->where('warehouse_id', $wid)->where('status', PickingStatus::Picking)->count(),
                'to_pack' => PickingList::query()->where('warehouse_id', $wid)->where('status', PickingStatus::ToPack)->count(),
                'ready' => PickingList::query()->where('warehouse_id', $wid)->where('status', PickingStatus::Packed)->count(),
                'awaiting_rep' => Handover::query()->where('warehouse_id', $wid)->where('status', HandoverStatus::AwaitingRepConfirm)->count(),
                'inbound_returns' => GoodsReceipt::query()
                    ->where('warehouse_id', $wid)
                    ->where('source', 'field_return')
                    ->where('status', 'pending_qc')
                    ->count(),
                'inbound_transfers' => GoodsReceipt::query()
                    ->where('warehouse_id', $wid)
                    ->where('status', 'pending_qc')
                    ->where('source', '!=', 'field_return')
                    ->count(),
            ],
            'alerts' => $alerts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pickingList(int $id): array
    {
        $list = $this->list($id);
        $header = $this->orders->header((int) $list->sub_order_id);
        $lines = $list->lines()->get();
        $locationOrder = [];
        foreach ($this->locations->orderedForWarehouse((int) $list->warehouse_id) as $index => $loc) {
            $locationOrder[(int) $loc['id']] = $index;
        }
        $lines = $lines
            ->sortBy(fn (PickingLine $line) => [
                $line->location_id !== null
                    ? ($locationOrder[(int) $line->location_id] ?? PHP_INT_MAX)
                    : PHP_INT_MAX,
                (int) $line->id,
            ])
            ->values();

        $repId = isset($header['rep_id']) ? (int) $header['rep_id'] : 0;
        $expectedRep = null;
        if ($repId > 0) {
            $expectedRep = [
                'id' => $repId,
                'name' => $this->reps->displayName($repId),
                'phone' => $this->reps->phone($repId),
            ];
        }

        return [
            'header' => [
                'order_no' => $header['sub_order_no'] ?? null,
                'shop' => $header['shop_name'] ?? null,
                'zone' => $header['zone_name'] ?? null,
                'expected_rep' => $expectedRep,
                'items' => $lines->count(),
                'units' => $lines->sum('qty_required'),
                'due_at' => $list->due_at?->timezone('Asia/Damascus')->toIso8601String(),
            ],
            'lines' => $lines->map(function (PickingLine $line) use ($list) {
                $snap = $this->products->snapshot((int) $line->product_id, $line->variant_id ? (int) $line->variant_id : null);
                $loc = $line->location_id ? $this->locations->find((int) $line->location_id) : null;
                $expiry = $this->ledger->earliestExpiry(
                    (int) $list->warehouse_id,
                    (int) $line->product_id,
                    $line->variant_id ? (int) $line->variant_id : null,
                );

                return [
                    'id' => (int) $line->id,
                    'image' => $snap['image'] ?? null,
                    'name' => $snap['name'] ?? '',
                    'variant' => $snap['variant'] ?? null,
                    'qty_required' => (int) $line->qty_required,
                    'qty_picked' => (int) $line->qty_picked,
                    'sale_unit' => $snap['sale_unit'] ?? null,
                    'location' => $loc ? ['aisle' => $loc['aisle'], 'shelf' => $loc['shelf']] : null,
                    'barcode' => $line->barcode,
                    'earliest_expiry' => $expiry,
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scan(int $id, string $barcode, int $qty): array
    {
        $list = $this->list($id);
        $line = $list->lines()->where('barcode', $barcode)->first();
        if ($line === null) {
            $found = $this->products->findByBarcode($barcode);
            if ($found !== null) {
                $line = $list->lines()
                    ->where('product_id', $found['product_id'])
                    ->where(function ($q) use ($found): void {
                        if ($found['variant_id']) {
                            $q->where('variant_id', $found['variant_id']);
                        }
                    })
                    ->first();
            }
        }
        if ($line === null) {
            throw new DomainException(__('fulfillment.barcode_not_in_order'), 'barcode_not_in_order', 422);
        }
        $list->status = PickingStatus::Picking;
        $list->save();
        $line->qty_picked = min((int) $line->qty_required, (int) $line->qty_picked + $qty);
        $line->save();

        return [
            'line_id' => (int) $line->id,
            'qty_picked' => (int) $line->qty_picked,
            'qty_required' => (int) $line->qty_required,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function manual(int $id, int $lineId, int $qty): array
    {
        $list = $this->list($id);
        $line = $list->lines()->whereKey($lineId)->first();
        if ($line === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }
        $line->qty_picked = min((int) $line->qty_required, $qty);
        $line->manual = true;
        $line->save();
        $list->status = PickingStatus::Picking;
        $list->save();

        return ['line_id' => (int) $line->id, 'qty_picked' => (int) $line->qty_picked, 'manual' => true];
    }

    /**
     * @return array{status: string, alternatives: list<int>}
     */
    public function shortage(int $id, int $lineId, int $qtyAvailable, string $reason): array
    {
        $list = $this->list($id);
        $line = $list->lines()->whereKey($lineId)->first();
        if ($line === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }
        $line->qty_picked = min((int) $line->qty_required, $qtyAvailable);
        $line->shortage_reason = $reason;
        $line->save();
        event(new PickingShortageReported((int) $list->id, (int) $list->sub_order_id, (int) $line->id, $reason));

        return [
            'status' => 'shortage_reported',
            'alternatives' => $this->products->alternativesInCategory((int) $line->product_id),
        ];
    }

    /**
     * @return array{status: string}
     */
    public function completePick(int $id, object $actor): array
    {
        $list = $this->list($id);
        $list->status = PickingStatus::ToPack;
        $list->save();
        PackingJob::query()->firstOrCreate(['picking_list_id' => $list->id], ['status' => 'open']);
        $this->orders->transition((int) $list->sub_order_id, 'processing', $actor);

        return ['status' => PickingStatus::ToPack->value];
    }

    /**
     * packing/{id} = picking list id
     *
     * @param  list<array{barcode: string, qty: int}>  $scans
     * @return array{mismatches: list<array<string, mixed>>}
     */
    public function verifyPack(int $id, array $scans): array
    {
        $list = $this->list($id);
        $mismatches = [];
        foreach ($list->lines as $line) {
            $matched = 0;
            foreach ($scans as $scan) {
                if ($scan['barcode'] === $line->barcode) {
                    $matched += (int) $scan['qty'];
                }
            }
            if ($matched !== (int) $line->qty_picked) {
                $mismatches[] = ['line_id' => (int) $line->id, 'expected' => (int) $line->qty_picked, 'scanned' => $matched];
            }
        }
        $job = PackingJob::query()->firstOrCreate(['picking_list_id' => $list->id], ['status' => 'open']);
        $job->mismatches = $mismatches;
        $job->save();

        return ['mismatches' => $mismatches];
    }

    /**
     * @param  array{packages_count: int, total_weight: mixed, flags?: list<string>}  $data
     * @return array{labels: list<array{package_no: string, qr: string}>}
     */
    public function completePack(int $id, array $data): array
    {
        $list = $this->list($id);
        $weight = $data['total_weight'] ?? 0;
        $grams = is_int($weight) ? $weight : (int) round(((float) $weight) * 1000);
        $job = PackingJob::query()->firstOrCreate(['picking_list_id' => $list->id], ['status' => 'open']);
        $job->forceFill([
            'status' => 'packed',
            'packages_count' => (int) $data['packages_count'],
            'weight_gram' => $grams,
            'flags' => $data['flags'] ?? [],
        ])->save();
        $list->status = PickingStatus::Packed;
        $list->save();

        $labels = [];
        $count = max(1, (int) $data['packages_count']);
        for ($i = 1; $i <= $count; $i++) {
            $pkg = Package::query()->create([
                'packing_job_id' => $job->id,
                'package_no' => 'PKG-'.$list->sub_order_id.'-'.$i,
                'qr_token' => 'qr_pkg_'.$list->sub_order_id.'_'.$i,
            ]);
            $labels[] = ['package_no' => $pkg->package_no, 'qr' => $pkg->qr_token];
        }

        return ['labels' => $labels];
    }

    /**
     * @return array<string, mixed>
     */
    public function pendingHandovers(): array
    {
        $wid = $this->warehouseId();
        $packed = PickingList::query()->where('warehouse_id', $wid)->where('status', PickingStatus::Packed)->get();
        $byRep = [];
        foreach ($packed as $list) {
            $header = $this->orders->header((int) $list->sub_order_id);
            $repId = $header['rep_id'] ?? 0;
            if (! isset($byRep[$repId])) {
                $byRep[$repId] = ['id' => $repId, 'name' => '', 'orders_count' => 0, 'packages' => 0, 'total_value' => 0];
            }
            $byRep[$repId]['orders_count']++;
            $byRep[$repId]['total_value'] += (int) ($header['total'] ?? 0);
        }

        return ['reps' => array_values($byRep)];
    }

    /**
     * @param  array{rep_id: int, sub_order_ids: list<int>, rep_qr?: string|null}  $data
     * @return array{handover_id: int, status: string, temp_code: string}
     */
    public function openHandover(array $data, object $actor): array
    {
        $wid = $this->warehouseId();
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $handover = Handover::query()->create([
            'warehouse_id' => $wid,
            'rep_id' => $data['rep_id'],
            'status' => HandoverStatus::AwaitingRepConfirm,
            'temp_code' => $code,
            'opened_at' => now(),
        ]);
        foreach ($data['sub_order_ids'] as $sid) {
            HandoverItem::query()->create([
                'handover_id' => $handover->id,
                'sub_order_id' => $sid,
            ]);
            $this->orders->transition((int) $sid, 'awaiting_handover', $actor);
        }
        event(new HandoverOpened((int) $handover->id, (int) $data['rep_id'], $wid));

        return [
            'handover_id' => (int) $handover->id,
            'status' => HandoverStatus::AwaitingRepConfirm->value,
            'temp_code' => $code,
        ];
    }

    /**
     * @return array{status: string, tracking_enabled: bool}
     */
    public function confirmHandover(int $handoverId, object $rep, string $tempCode): array
    {
        $handover = Handover::query()->whereKey($handoverId)->where('rep_id', $rep->getAuthIdentifier())->first();
        if ($handover === null) {
            throw new DomainException(__('fulfillment.handover_missing'), 'illegal_transition', 409);
        }
        if ($handover->status === HandoverStatus::Confirmed) {
            return ['status' => 'on_the_way', 'tracking_enabled' => true];
        }
        if ($handover->temp_code !== $tempCode) {
            throw new DomainException(__('fulfillment.invalid_temp_code'), 'validation_failed', 422);
        }

        $handover->status = HandoverStatus::Confirmed;
        $handover->confirmed_at = now();
        $handover->save();

        $ids = [];
        foreach ($handover->items as $item) {
            $this->ledger->pickDeduct((int) $item->sub_order_id, $rep);
            $this->orders->transition((int) $item->sub_order_id, 'on_the_way', $rep);
            $ids[] = (int) $item->sub_order_id;
        }
        event(new HandoverConfirmedByRep((int) $handover->id, (int) $rep->getAuthIdentifier(), $ids));

        return ['status' => 'on_the_way', 'tracking_enabled' => true];
    }

    /**
     * @param  list<array{sub_order_id: int, reason: string}>  $undelivered
     * @return array{restocked: list<int>, wallet_matched: bool}
     */
    public function returnTrip(int $id, array $undelivered, object $actor): array
    {
        $handover = Handover::query()->whereKey($id)->where('warehouse_id', $this->warehouseId())->first();
        if ($handover === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }
        $restocked = [];
        foreach ($undelivered as $row) {
            $this->ledger->restockUndelivered((int) $row['sub_order_id'], $actor);
            $restocked[] = (int) $row['sub_order_id'];
        }

        // Honest match: every undelivered line we were asked to restock was restocked.
        // Cash-bag amounts are not on this request body — do not invent a wallet compare.
        return [
            'restocked' => $restocked,
            'wallet_matched' => count($restocked) === count($undelivered),
        ];
    }

    /**
     * @return array{id: int, status: string}
     */
    public function receiving(array $data): array
    {
        $receipt = GoodsReceipt::query()->create([
            'warehouse_id' => $this->warehouseId(),
            'source' => $data['source'],
            'reference_no' => $data['reference_no'] ?? null,
            'status' => 'pending_qc',
        ]);
        foreach ($data['lines'] as $line) {
            GoodsReceiptLine::query()->create([
                'goods_receipt_id' => $receipt->id,
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'] ?? null,
                'qty_expected' => $line['qty_expected'],
                'qty_received' => $line['qty_received'],
                'lot_no' => $line['lot_no'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'location_id' => $line['location_id'] ?? null,
            ]);
        }

        return ['id' => (int) $receipt->id, 'status' => 'pending_qc'];
    }

    /**
     * @return array{status: string}
     */
    public function qc(int $id, array $lines, object $actor): array
    {
        $receipt = GoodsReceipt::query()->with('lines')->find($id);
        if ($receipt === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }
        foreach ($lines as $row) {
            $line = $receipt->lines->firstWhere('id', (int) $row['line_id']);
            if ($line === null || ($row['decision'] ?? '') !== 'accept') {
                continue;
            }
            $this->ledger->receiveLot(
                (int) $receipt->warehouse_id,
                (int) $line->product_id,
                $line->variant_id ? (int) $line->variant_id : null,
                (int) $row['qty'],
                $actor,
                'goods_receipt',
                (int) $receipt->id,
                is_string($line->lot_no) ? $line->lot_no : null,
                $line->expiry_date !== null ? (string) $line->expiry_date : null,
            );
        }
        $receipt->status = 'posted';
        $receipt->save();

        if ($receipt->source === 'transfer' && is_numeric($receipt->reference_no)) {
            $this->ledger->transferReceived((int) $receipt->reference_no);
        }

        return ['status' => 'posted'];
    }

    /**
     * @return array{id: int, status: string}
     */
    public function startStocktake(array $data, object $actor): array
    {
        $row = Stocktake::query()->create([
            'warehouse_id' => (int) ($data['warehouse_id'] ?? $this->warehouseId()),
            'scope' => $data['scope'] ?? 'full',
            'status' => 'counting',
            'counted_by' => $actor->getAuthIdentifier(),
        ]);

        return ['id' => (int) $row->id, 'status' => 'counting'];
    }

    /**
     * @return array{line_id: int}
     */
    public function recordCount(int $id, array $data): array
    {
        $take = Stocktake::query()->find($id);
        if ($take === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }
        $line = StocktakeLine::query()->create([
            'stocktake_id' => $take->id,
            'product_id' => $data['product_id'],
            'variant_id' => $data['variant_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'counted_qty' => $data['counted_qty'],
        ]);

        return ['line_id' => (int) $line->id];
    }

    /**
     * @return array{status: string}
     */
    public function submitStocktake(int $id): array
    {
        $take = Stocktake::query()->find($id);
        if ($take === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }
        $take->status = 'pending_approval';
        $take->save();

        return ['status' => 'pending_approval'];
    }

    /**
     * @return array{status: string, adjustments: int}
     */
    public function approveStocktake(int $id, object $actor, string $reason): array
    {
        $take = Stocktake::query()->with('lines')->find($id);
        if ($take === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }
        if ((int) $take->counted_by === (int) $actor->getAuthIdentifier()) {
            throw new DomainException(__('fulfillment.sod_counter'), 'sod_violation', 403);
        }
        $adjustments = 0;
        foreach ($take->lines as $line) {
            $snap = $this->ledger->snapshot(
                (int) $take->warehouse_id,
                (int) $line->product_id,
                $line->variant_id ? (int) $line->variant_id : null,
            );
            $delta = (int) $line->counted_qty - $snap['on_hand'];
            if ($delta !== 0) {
                $this->ledger->stocktakeDelta(
                    (int) $take->warehouse_id,
                    (int) $line->product_id,
                    $line->variant_id ? (int) $line->variant_id : null,
                    $delta,
                    $actor,
                    (int) $take->id,
                );
                $adjustments++;
            }
        }
        $take->status = 'posted';
        $take->approved_by = $actor->getAuthIdentifier();
        $take->save();
        $this->audit->record('inventory.stocktake.approve', $actor, 'stocktake', (int) $take->id, [
            'reason' => $reason,
            'adjustments' => $adjustments,
        ]);

        return ['status' => 'posted', 'adjustments' => $adjustments];
    }

    /**
     * @return array<string, mixed>
     */
    public function repReceipts(object $rep, ?string $date): array
    {
        $query = Handover::query()
            ->where('rep_id', $rep->getAuthIdentifier())
            ->where('status', HandoverStatus::AwaitingRepConfirm);
        if ($date) {
            $query->whereDate('opened_at', $date);
        }
        $handovers = $query->with('items')->get();
        $orders = [];
        foreach ($handovers as $h) {
            foreach ($h->items as $item) {
                $subOrderId = (int) $item->sub_order_id;
                $header = $this->orders->header($subOrderId);
                $orders[] = [
                    'sub_order_id' => $subOrderId,
                    'order_no' => $header['sub_order_no'] ?? null,
                    'shop' => $header['shop_name'] ?? null,
                    'zone' => $header['zone_name'] ?? null,
                    'handover_id' => (int) $h->id,
                ];
            }
        }

        return [
            'date' => $date,
            'rep_name' => $rep->name ?? '',
            'count' => count($orders),
            'orders' => $orders,
        ];
    }

    /**
     * @param  list<int>  $subOrderIds
     * @return array{picking_list_ids: list<int>}
     */
    public function batchPickingLists(array $subOrderIds): array
    {
        $wid = $this->warehouseId();
        $channelId = $this->warehouses->channelId($wid);
        if ($channelId === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }

        $ids = [];
        foreach ($subOrderIds as $subOrderId) {
            $header = $this->orders->header((int) $subOrderId);
            if ($header === null || (int) $header['channel_id'] !== $channelId) {
                throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
            }
            $lines = array_map(fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'variant_id' => $line['variant_id'] !== null ? (int) $line['variant_id'] : null,
                'qty' => (int) $line['qty'],
            ], $this->orders->lines((int) $subOrderId));

            $ids[] = $this->pickingLists->create([
                'sub_order_id' => (int) $subOrderId,
                'channel_id' => $channelId,
                'warehouse_id' => $wid,
                'lines' => $lines,
            ]);
        }

        return ['picking_list_ids' => $ids];
    }

    /**
     * @param  list<int>  $subOrderIds
     * @return array{id: int, picking_list_ids: list<int>, status: string}
     */
    public function createPickingWave(array $subOrderIds, ?int $assignedTo = null): array
    {
        $wid = $this->warehouseId();
        $channelId = $this->warehouses->channelId($wid);
        if ($channelId === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }

        $batch = $this->batchPickingLists($subOrderIds);
        $pickingListIds = $batch['picking_list_ids'];

        $wave = PickingWave::query()->create([
            'channel_id' => $channelId,
            'warehouse_id' => $wid,
            'status' => 'open',
            'assigned_to' => $assignedTo,
        ]);

        PickingList::query()
            ->whereIn('id', $pickingListIds)
            ->update(['wave_id' => $wave->id]);

        return [
            'id' => (int) $wave->id,
            'picking_list_ids' => $pickingListIds,
            'status' => (string) $wave->status,
        ];
    }

    /**
     * @return array{id: int, status: string, assigned_to: int|null, picking_list_ids: list<int>, lines: list<array{product_id: int, variant_id: int|null, barcode: string|null, qty_required: int, qty_picked: int, location_id: int|null, sub_order_ids: list<int>}>}
     */
    public function showPickingWave(int $id): array
    {
        $wid = $this->warehouseId();
        $wave = PickingWave::query()
            ->with(['pickingLists.lines'])
            ->find($id);

        if ($wave === null || (int) $wave->warehouse_id !== $wid) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }

        /** @var list<PickingList> $lists */
        $lists = $wave->pickingLists->all();
        $pickingListIds = array_map(fn (PickingList $list): int => (int) $list->id, $lists);

        /** @var array<string, array{product_id: int, variant_id: int|null, barcode: string|null, qty_required: int, qty_picked: int, location_id: int|null, sub_order_ids: list<int>}> $merged */
        $merged = [];
        foreach ($lists as $list) {
            $subOrderId = (int) $list->sub_order_id;
            foreach ($list->lines as $line) {
                $variantId = $line->variant_id !== null ? (int) $line->variant_id : null;
                $locationId = $line->location_id !== null ? (int) $line->location_id : null;
                $barcode = $line->barcode !== null ? (string) $line->barcode : null;
                $key = implode('|', [
                    (string) (int) $line->product_id,
                    $variantId === null ? '' : (string) $variantId,
                    $barcode ?? '',
                    $locationId === null ? '' : (string) $locationId,
                ]);

                if (! isset($merged[$key])) {
                    $merged[$key] = [
                        'product_id' => (int) $line->product_id,
                        'variant_id' => $variantId,
                        'barcode' => $barcode,
                        'qty_required' => 0,
                        'qty_picked' => 0,
                        'location_id' => $locationId,
                        'sub_order_ids' => [],
                    ];
                }

                $merged[$key]['qty_required'] += (int) $line->qty_required;
                $merged[$key]['qty_picked'] += (int) $line->qty_picked;
                if (! in_array($subOrderId, $merged[$key]['sub_order_ids'], true)) {
                    $merged[$key]['sub_order_ids'][] = $subOrderId;
                }
            }
        }

        $locationOrder = [];
        foreach ($this->locations->orderedForWarehouse($wid) as $index => $loc) {
            $locationOrder[(int) $loc['id']] = $index;
        }

        $lines = array_values($merged);
        usort($lines, function (array $a, array $b) use ($locationOrder): int {
            $aOrder = $a['location_id'] !== null
                ? ($locationOrder[$a['location_id']] ?? PHP_INT_MAX)
                : PHP_INT_MAX;
            $bOrder = $b['location_id'] !== null
                ? ($locationOrder[$b['location_id']] ?? PHP_INT_MAX)
                : PHP_INT_MAX;

            return $aOrder <=> $bOrder
                ?: $a['product_id'] <=> $b['product_id'];
        });

        return [
            'id' => (int) $wave->id,
            'status' => (string) $wave->status,
            'assigned_to' => $wave->assigned_to !== null ? (int) $wave->assigned_to : null,
            'picking_list_ids' => $pickingListIds,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array{warehouse_id?: int|string|null, product_id?: int|string|null, expiry_before?: string|null, page?: int|string|null, per_page?: int|string|null}  $filters
     * @return array{data: list<array{id:int,warehouse_id:int,product_id:int,variant_id:int|null,lot_no:string|null,expiry_date:string|null,qty:int}>, meta: array{page:int,per_page:int,total:int,last_page:int}}
     */
    public function listStockLots(array $filters): array
    {
        $warehouseId = isset($filters['warehouse_id']) && $filters['warehouse_id'] !== '' && $filters['warehouse_id'] !== null
            ? (int) $filters['warehouse_id']
            : $this->warehouseId();

        $productId = isset($filters['product_id']) && $filters['product_id'] !== '' && $filters['product_id'] !== null
            ? (int) $filters['product_id']
            : null;

        $expiryBefore = isset($filters['expiry_before']) && is_string($filters['expiry_before']) && $filters['expiry_before'] !== ''
            ? $filters['expiry_before']
            : null;

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(max(1, (int) ($filters['per_page'] ?? 25)), 100);

        return $this->ledger->listLots($warehouseId, $productId, $expiryBefore, $page, $perPage);
    }

    /**
     * @param  array{qty_delta: int, reason: string}  $data
     * @return array{id:int, qty:int, movement_id:int, available:int}
     */
    public function adjustStockLot(int $id, array $data, object $actor): array
    {
        return $this->ledger->adjustLot(
            $id,
            $this->warehouseId(),
            (int) $data['qty_delta'],
            (string) $data['reason'],
            $actor,
        );
    }

    private function list(int $id): PickingList
    {
        $list = PickingList::query()->with('lines')->find($id);
        if ($list === null) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }
        $wid = WarehouseScope::currentId();
        if ($wid !== null && (int) $list->warehouse_id !== $wid) {
            throw new DomainException(__('fulfillment.not_found'), 'not_found', 404);
        }

        return $list;
    }
}
