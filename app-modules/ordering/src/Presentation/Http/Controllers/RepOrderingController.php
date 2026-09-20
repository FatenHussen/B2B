<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Ordering\Application\Actions\AcceptAssignment;
use Modules\Ordering\Application\Actions\AddRepCartLine;
use Modules\Ordering\Application\Actions\RejectAssignment;
use Modules\Ordering\Application\Actions\SubmitRepCartSection;
use Modules\Ordering\Application\Queries\ListRepAssignments;
use Modules\Ordering\Application\Queries\ListRepOrders;
use Modules\Ordering\Application\Queries\ListRepScheduledOrders;
use Modules\Ordering\Application\Queries\ShowRepCart;
use Modules\Ordering\Presentation\Http\Requests\AddRepCartLineRequest;
use Modules\Ordering\Presentation\Http\Requests\RejectAssignmentRequest;
use Modules\Ordering\Presentation\Http\Requests\SubmitRepCartRequest;

final class RepOrderingController extends ApiController
{
    public function addLine(AddRepCartLineRequest $request, AddRepCartLine $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function cart(Request $request, ShowRepCart $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }

    public function submit(SubmitRepCartRequest $request, SubmitRepCartSection $action, int $retailerId): JsonResponse
    {
        return $this->ok($action($request->user(), $retailerId, $request->validated()));
    }

    public function assignments(Request $request, ListRepAssignments $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }

    public function accept(Request $request, AcceptAssignment $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id));
    }

    public function reject(RejectAssignmentRequest $request, RejectAssignment $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function scheduled(Request $request, ListRepScheduledOrders $query): JsonResponse
    {
        return $this->ok($query($request->user(), $request->query('date')));
    }

    public function orders(Request $request, ListRepOrders $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }
}
