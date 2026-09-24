<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Fulfillment\Application\Actions\PushWarehouseSyncOperations;
use Modules\Fulfillment\Application\Actions\ResolveWarehouseSyncConflict;
use Modules\Fulfillment\Application\Queries\ShowWarehouseSyncStatus;
use Modules\Fulfillment\Presentation\Http\Requests\PushWarehouseSyncRequest;
use Modules\Fulfillment\Presentation\Http\Requests\ResolveWarehouseSyncConflictRequest;

final class WarehouseSyncController extends ApiController
{
    public function push(PushWarehouseSyncRequest $request, PushWarehouseSyncOperations $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated(), $request->header('X-Device-Id')));
    }

    public function status(Request $request, ShowWarehouseSyncStatus $query): JsonResponse
    {
        return $this->ok($query($request->user(), $request));
    }

    public function resolve(ResolveWarehouseSyncConflictRequest $request, ResolveWarehouseSyncConflict $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }
}
