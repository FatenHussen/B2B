<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Fulfillment\Application\WarehouseWorkspace;
use Modules\Fulfillment\Presentation\Http\Requests\AdjustStockLotRequest;
use Modules\Fulfillment\Presentation\Http\Requests\ApproveStocktakeRequest;
use Modules\Fulfillment\Presentation\Http\Requests\BatchPickingListsRequest;
use Modules\Fulfillment\Presentation\Http\Requests\CompletePackingRequest;
use Modules\Fulfillment\Presentation\Http\Requests\ConfirmHandoverRequest;
use Modules\Fulfillment\Presentation\Http\Requests\CreateHandoverRequest;
use Modules\Fulfillment\Presentation\Http\Requests\CreatePickingWaveRequest;
use Modules\Fulfillment\Presentation\Http\Requests\CreateReceivingRequest;
use Modules\Fulfillment\Presentation\Http\Requests\ManualPickRequest;
use Modules\Fulfillment\Presentation\Http\Requests\QcReceivingRequest;
use Modules\Fulfillment\Presentation\Http\Requests\RecordCountRequest;
use Modules\Fulfillment\Presentation\Http\Requests\ReturnTripRequest;
use Modules\Fulfillment\Presentation\Http\Requests\ScanPickRequest;
use Modules\Fulfillment\Presentation\Http\Requests\ShortageRequest;
use Modules\Fulfillment\Presentation\Http\Requests\StartStocktakeRequest;
use Modules\Fulfillment\Presentation\Http\Requests\VerifyPackingRequest;

final class WarehouseController extends ApiController
{
    public function queues(WarehouseWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->queues());
    }

    public function batchPicking(BatchPickingListsRequest $request, WarehouseWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->batchPickingLists($request->validated()['sub_order_ids']));
    }

    public function createPickingWave(CreatePickingWaveRequest $request, WarehouseWorkspace $ops): JsonResponse
    {
        $v = $request->validated();
        $assignedTo = array_key_exists('assigned_to', $v) && $v['assigned_to'] !== null
            ? (int) $v['assigned_to']
            : null;

        return $this->ok($ops->createPickingWave($v['sub_order_ids'], $assignedTo));
    }

    public function showPickingWave(WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->showPickingWave($id));
    }

    public function picking(WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->pickingList($id));
    }

    public function scan(ScanPickRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        $v = $request->validated();

        return $this->ok($ops->scan($id, $v['barcode'], (int) $v['qty']));
    }

    public function manual(ManualPickRequest $request, WarehouseWorkspace $ops, int $id, int $lineId): JsonResponse
    {
        return $this->ok($ops->manual($id, $lineId, (int) $request->validated()['qty']));
    }

    public function shortage(ShortageRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        $v = $request->validated();

        return $this->ok($ops->shortage($id, (int) $v['line_id'], (int) $v['qty_available'], $v['reason']));
    }

    public function completePick(Request $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->completePick($id, $request->user()));
    }

    public function verifyPack(VerifyPackingRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->verifyPack($id, $request->validated()['scans']));
    }

    public function completePack(CompletePackingRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->completePack($id, $request->validated()));
    }

    public function pendingHandovers(WarehouseWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->pendingHandovers());
    }

    public function createHandover(CreateHandoverRequest $request, WarehouseWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->openHandover($request->validated(), $request->user()));
    }

    public function returnTrip(ReturnTripRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->returnTrip($id, $request->validated()['undelivered'] ?? [], $request->user()));
    }

    public function receiving(CreateReceivingRequest $request, WarehouseWorkspace $ops): JsonResponse
    {
        return $this->created($ops->receiving($request->validated()));
    }

    public function qc(QcReceivingRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->qc($id, $request->validated()['lines'], $request->user()));
    }

    public function startStocktake(StartStocktakeRequest $request, WarehouseWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->startStocktake($request->validated(), $request->user()));
    }

    public function recordCount(RecordCountRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->recordCount($id, $request->validated()));
    }

    public function submitStocktake(WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->submitStocktake($id));
    }

    public function approveStocktake(ApproveStocktakeRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->approveStocktake($id, $request->user(), $request->validated()['reason'] ?? ''));
    }

    public function receipts(Request $request, WarehouseWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->repReceipts($request->user(), $request->query('date')));
    }

    public function confirm(ConfirmHandoverRequest $request, WarehouseWorkspace $ops, int $handoverId): JsonResponse
    {
        return $this->ok($ops->confirmHandover($handoverId, $request->user(), $request->validated()['temp_code']));
    }

    public function stockLots(Request $request, WarehouseWorkspace $ops): JsonResponse
    {
        $result = $ops->listStockLots($request->query());

        return $this->ok($result['data'], $result['meta']);
    }

    public function adjustStockLot(AdjustStockLotRequest $request, WarehouseWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->adjustStockLot($id, $request->validated(), $request->user()));
    }
}
