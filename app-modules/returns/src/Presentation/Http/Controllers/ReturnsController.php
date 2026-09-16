<?php

declare(strict_types=1);

namespace Modules\Returns\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Returns\Application\Queries\ListChannelReturnRequests;
use Modules\Returns\Application\ReturnsWorkspace;
use Modules\Returns\Presentation\Http\Requests\CreateReturnRequest;
use Modules\Returns\Presentation\Http\Requests\DecideReturnRequest;
use Modules\Returns\Presentation\Http\Requests\SortReturnRequest;

final class ReturnsController extends ApiController
{
    public function createForRetailer(CreateReturnRequest $request, ReturnsWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->create($request->user(), $request->validated(), true));
    }

    public function createForRep(CreateReturnRequest $request, ReturnsWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->create($request->user(), $request->validated(), false));
    }

    public function retailerIndex(Request $request, ReturnsWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->listForRetailer($request->user()));
    }

    public function channelIndex(ListChannelReturnRequests $query, Request $request): JsonResponse
    {
        return $this->paginated($query($request), fn ($row) => $query->map($row));
    }

    public function decide(DecideReturnRequest $request, ReturnsWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->decide($id, $request->validated(), $request->user()));
    }

    public function sort(SortReturnRequest $request, ReturnsWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->sort($id, $request->validated()['lines'], $request->user()));
    }
}
