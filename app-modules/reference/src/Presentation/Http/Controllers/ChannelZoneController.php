<?php

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Actions\UpsertChannelZoneCoverage;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Presentation\Http\Requests\UpsertChannelZoneRequest;
use Modules\Reference\Presentation\Http\Resources\ChannelZoneResource;

class ChannelZoneController extends ApiController
{
    public function index(): JsonResponse
    {
        $coverage = ChannelZone::query()->with('zone')->orderBy('id')->get();

        return $this->ok(ChannelZoneResource::collection($coverage));
    }

    public function store(UpsertChannelZoneRequest $request, UpsertChannelZoneCoverage $action): JsonResponse
    {
        $coverage = $action->handle(
            $request->integer('zone_id'),
            $request->safe()->except('zone_id'),
        );

        return $this->created(new ChannelZoneResource($coverage->load('zone')));
    }

    public function destroy(ChannelZone $channelZone): JsonResponse
    {
        $channelZone->delete();

        return $this->noContent();
    }
}
