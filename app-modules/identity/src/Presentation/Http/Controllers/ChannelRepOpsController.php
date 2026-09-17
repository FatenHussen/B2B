<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\ApproveChannelRep;
use Modules\Identity\Application\Actions\DecideRepSourcedShop;
use Modules\Identity\Application\Actions\DecideRepZoneRequest;
use Modules\Identity\Application\Actions\DisableChannelRep;
use Modules\Identity\Application\Actions\RejectChannelRep;
use Modules\Identity\Application\Queries\ListChannelReps;
use Modules\Identity\Application\Queries\ListChannelRepSourcedShops;
use Modules\Identity\Application\Queries\ListChannelRepZoneRequests;
use Modules\Identity\Application\Queries\ShowChannelRep;
use Modules\Identity\Presentation\Http\Requests\ApproveChannelRepRequest;
use Modules\Identity\Presentation\Http\Requests\DecideRepQueueRequest;
use Modules\Identity\Presentation\Http\Requests\ReasonRequiredChannelRepRequest;

final class ChannelRepOpsController extends ApiController
{
    public function index(ListChannelReps $query, Request $request): JsonResponse
    {
        return $this->paginated($query($request), fn ($row) => $query->map($row));
    }

    public function show(ShowChannelRep $query, int $id): JsonResponse
    {
        return $this->ok($query($id));
    }

    public function approve(ApproveChannelRepRequest $request, ApproveChannelRep $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function reject(ReasonRequiredChannelRepRequest $request, RejectChannelRep $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function disable(ReasonRequiredChannelRepRequest $request, DisableChannelRep $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function zoneRequests(ListChannelRepZoneRequests $query, Request $request): JsonResponse
    {
        return $this->paginated($query($request), fn ($row) => $query->map($row));
    }

    public function decideZoneRequest(DecideRepQueueRequest $request, DecideRepZoneRequest $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function sourcedShops(ListChannelRepSourcedShops $query, Request $request): JsonResponse
    {
        return $this->paginated($query($request), fn ($row) => $query->map($row));
    }

    public function decideSourcedShop(DecideRepQueueRequest $request, DecideRepSourcedShop $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }
}
