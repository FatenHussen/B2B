<?php

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Reference\Domain\Models\Zone;
use Modules\Reference\Presentation\Http\Requests\StoreZoneRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateZoneRequest;
use Modules\Reference\Presentation\Http\Resources\ZoneResource;

class ZoneController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $zones = Zone::query()
            ->when($request->integer('governorate_id'), fn ($q, $id) => $q->where('governorate_id', $id))
            ->orderBy('name')
            ->get();

        return $this->ok(ZoneResource::collection($zones));
    }

    public function show(Zone $zone): JsonResponse
    {
        return $this->ok(new ZoneResource($zone));
    }

    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zone = Zone::create($request->validated());

        return $this->created(new ZoneResource($zone));
    }

    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $zone->update($request->validated());

        return $this->ok(new ZoneResource($zone));
    }

    public function destroy(Zone $zone): JsonResponse
    {
        $zone->delete();

        return $this->noContent();
    }
}
