<?php

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Presentation\Http\Requests\StoreGovernorateRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateGovernorateRequest;
use Modules\Reference\Presentation\Http\Resources\GovernorateResource;

class GovernorateController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->ok(GovernorateResource::collection(
            Governorate::query()->orderBy('name_ar')->get()
        ));
    }

    public function show(Governorate $governorate): JsonResponse
    {
        return $this->ok(new GovernorateResource($governorate));
    }

    public function store(StoreGovernorateRequest $request): JsonResponse
    {
        $governorate = Governorate::create($request->validated());

        return $this->created(new GovernorateResource($governorate));
    }

    public function update(UpdateGovernorateRequest $request, Governorate $governorate): JsonResponse
    {
        $governorate->update($request->validated());

        return $this->ok(new GovernorateResource($governorate));
    }

    public function destroy(Governorate $governorate): JsonResponse
    {
        $governorate->delete();

        return $this->noContent();
    }
}
