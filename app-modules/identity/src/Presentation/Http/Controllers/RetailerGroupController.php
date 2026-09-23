<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\SaveRetailerGroup;
use Modules\Identity\Application\Queries\ListRetailerGroups;
use Modules\Identity\Presentation\Http\Requests\StoreRetailerGroupRequest;

final class RetailerGroupController extends ApiController
{
    public function index(ListRetailerGroups $query, Request $request): JsonResponse
    {
        return $this->paginated($query($request), fn ($row) => $query->map($row));
    }

    public function store(StoreRetailerGroupRequest $request, SaveRetailerGroup $action): JsonResponse
    {
        return $this->created($action->create($request->user(), $request->validated()));
    }

    public function update(StoreRetailerGroupRequest $request, SaveRetailerGroup $action, int $id): JsonResponse
    {
        return $this->ok($action->update($request->user(), $id, $request->validated()));
    }

    public function destroy(Request $request, SaveRetailerGroup $action, int $id): JsonResponse
    {
        return $this->ok($action->delete($request->user(), $id));
    }
}
