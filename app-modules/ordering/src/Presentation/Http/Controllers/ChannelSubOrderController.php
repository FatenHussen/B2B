<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Ordering\Application\Actions\AssignSubOrders;
use Modules\Ordering\Application\Actions\BulkConfirmSubOrders;
use Modules\Ordering\Application\Actions\CancelSubOrder;
use Modules\Ordering\Application\Actions\ConfirmSubOrder;
use Modules\Ordering\Application\Actions\EditSubOrderLines;
use Modules\Ordering\Application\Actions\RejectSubOrder;
use Modules\Ordering\Application\Actions\ScheduleSubOrder;
use Modules\Ordering\Application\Queries\ListChannelSubOrders;
use Modules\Ordering\Application\Queries\ShowChannelSubOrder;
use Modules\Ordering\Presentation\Http\Requests\AssignSubOrdersRequest;
use Modules\Ordering\Presentation\Http\Requests\BulkConfirmRequest;
use Modules\Ordering\Presentation\Http\Requests\CancelOrderRequest;
use Modules\Ordering\Presentation\Http\Requests\EditSubOrderLinesRequest;
use Modules\Ordering\Presentation\Http\Requests\ReassignSubOrderRequest;
use Modules\Ordering\Presentation\Http\Requests\RejectSubOrderRequest;
use Modules\Ordering\Presentation\Http\Requests\ScheduleSubOrderRequest;

final class ChannelSubOrderController extends ApiController
{
    public function index(Request $request, ListChannelSubOrders $query): JsonResponse
    {
        return $this->paginated($query($request), fn ($row) => $query->map($row));
    }

    public function show(Request $request, ShowChannelSubOrder $query, int $id): JsonResponse
    {
        return $this->ok($query($request->user(), $id));
    }

    public function confirm(Request $request, ConfirmSubOrder $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id));
    }

    public function bulkConfirm(BulkConfirmRequest $request, BulkConfirmSubOrders $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function reject(RejectSubOrderRequest $request, RejectSubOrder $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function editLines(EditSubOrderLinesRequest $request, EditSubOrderLines $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function assign(AssignSubOrdersRequest $request, AssignSubOrders $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function reassign(ReassignSubOrderRequest $request, AssignSubOrders $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated(), true, $id));
    }

    public function schedule(ScheduleSubOrderRequest $request, ScheduleSubOrder $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function cancel(CancelOrderRequest $request, CancelSubOrder $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }
}
