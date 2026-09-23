<?php

declare(strict_types=1);

namespace Modules\Sync\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Sync\Application\Actions\PushSyncOperations;
use Modules\Sync\Application\Actions\ResolveSyncConflict;
use Modules\Sync\Application\Queries\PullSync;
use Modules\Sync\Application\Queries\ShowSyncStatus;
use Modules\Sync\Presentation\Http\Requests\PushSyncRequest;
use Modules\Sync\Presentation\Http\Requests\ResolveSyncConflictRequest;

final class AppSyncController extends ApiController
{
    public function pull(Request $request, PullSync $query): JsonResponse
    {
        return $this->ok($query($request->user(), $request));
    }

    public function push(PushSyncRequest $request, PushSyncOperations $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated(), $request->header('X-Device-Id')));
    }

    public function status(Request $request, ShowSyncStatus $query): JsonResponse
    {
        return $this->ok($query($request->user(), $request));
    }

    public function resolve(ResolveSyncConflictRequest $request, ResolveSyncConflict $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }
}
