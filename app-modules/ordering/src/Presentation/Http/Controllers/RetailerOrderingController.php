<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Ordering\Application\Actions\AddRetailerCartLine;
use Modules\Ordering\Application\Actions\CancelSubOrder;
use Modules\Ordering\Application\Actions\RemoveRetailerCartLine;
use Modules\Ordering\Application\Actions\ReorderSubOrder;
use Modules\Ordering\Application\Actions\SubmitRetailerCart;
use Modules\Ordering\Application\Actions\UpdateRetailerCartLine;
use Modules\Ordering\Application\Actions\UpdateRetailerCartSection;
use Modules\Ordering\Application\Queries\ListRetailerOrders;
use Modules\Ordering\Application\Queries\ShowRetailerCart;
use Modules\Ordering\Application\Queries\ShowRetailerOrder;
use Modules\Ordering\Application\Queries\TrackRetailerOrder;
use Modules\Ordering\Presentation\Http\Requests\AddCartLineRequest;
use Modules\Ordering\Presentation\Http\Requests\CancelOrderRequest;
use Modules\Ordering\Presentation\Http\Requests\SubmitCartRequest;
use Modules\Ordering\Presentation\Http\Requests\UpdateCartLineRequest;
use Modules\Ordering\Presentation\Http\Requests\UpdateCartSectionRequest;

final class RetailerOrderingController extends ApiController
{
    public function cart(Request $request, ShowRetailerCart $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }

    public function addLine(AddCartLineRequest $request, AddRetailerCartLine $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function updateLine(UpdateCartLineRequest $request, UpdateRetailerCartLine $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function deleteLine(Request $request, RemoveRetailerCartLine $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id));
    }

    public function updateSection(UpdateCartSectionRequest $request, UpdateRetailerCartSection $action, string $ref): JsonResponse
    {
        return $this->ok($action($request->user(), $ref, $request->validated()));
    }

    public function submit(SubmitCartRequest $request, SubmitRetailerCart $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function orders(Request $request, ListRetailerOrders $query): JsonResponse
    {
        return $this->paginated($query($request->user(), $request), fn ($row) => $query->map($row));
    }

    public function show(Request $request, ShowRetailerOrder $query, int $id): JsonResponse
    {
        return $this->ok($query($request->user(), $id));
    }

    public function cancel(CancelOrderRequest $request, CancelSubOrder $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated(), true));
    }

    public function reorder(Request $request, ReorderSubOrder $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id));
    }

    public function tracking(Request $request, TrackRetailerOrder $query, int $id): JsonResponse
    {
        return $this->ok($query($request->user(), $id));
    }
}
