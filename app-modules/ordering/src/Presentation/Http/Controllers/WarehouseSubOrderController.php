<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Ordering\Application\Actions\AllocateStockShortage;
use Modules\Ordering\Application\Actions\AssignSubOrders;
use Modules\Ordering\Application\Actions\ConfirmSubOrder;
use Modules\Ordering\Application\Queries\ListChannelSubOrders;
use Modules\Ordering\Presentation\Http\Requests\AllocateStockShortageRequest;
use Modules\Ordering\Presentation\Http\Requests\AssignSubOrdersRequest;

final class WarehouseSubOrderController extends ApiController
{
    public function index(Request $request, ListChannelSubOrders $query): JsonResponse
    {
        return $this->paginated($query($request), fn ($row) => $query->map($row));
    }

    public function confirm(Request $request, ConfirmSubOrder $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, 'warehouse'));
    }

    public function assign(AssignSubOrdersRequest $request, AssignSubOrders $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function allocateShortage(AllocateStockShortageRequest $request, AllocateStockShortage $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }
}
