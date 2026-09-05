<?php

declare(strict_types=1);

namespace Modules\Inventory\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Inventory\Application\Actions\AdjustStock;
use Modules\Inventory\Application\Actions\CreateStockTransfer;
use Modules\Inventory\Application\Actions\UpsertReorderPoints;
use Modules\Inventory\Application\Queries\ListStockLevels;
use Modules\Inventory\Application\Queries\ListStockMovements;
use Modules\Inventory\Presentation\Http\Requests\AdjustStockRequest;
use Modules\Inventory\Presentation\Http\Requests\StoreTransferRequest;
use Modules\Inventory\Presentation\Http\Requests\UpsertReorderPointsRequest;

final class ChannelInventoryController extends ApiController
{
    public function levels(ListStockLevels $query): JsonResponse
    {
        return $this->paginated($query(), fn ($row) => $query->map($row));
    }

    public function adjust(AdjustStockRequest $request, AdjustStock $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function transfer(StoreTransferRequest $request, CreateStockTransfer $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function movements(ListStockMovements $query): JsonResponse
    {
        return $this->paginated($query(), fn ($row) => $query->map($row));
    }

    public function reorderPoints(UpsertReorderPointsRequest $request, UpsertReorderPoints $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }
}
