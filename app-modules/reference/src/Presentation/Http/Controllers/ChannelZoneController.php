<?php

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Actions\UpsertChannelZoneCoverage;
use Modules\Reference\Application\Queries\ShowChannelZonesMap;
use Modules\Reference\Application\Queries\ShowDeliveryCalendar;
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

    public function deliveryCalendar(Request $request, ShowDeliveryCalendar $query): JsonResponse
    {
        $week = $request->query('week');

        return $this->ok($query(is_string($week) ? $week : null));
    }

    public function map(ShowChannelZonesMap $query): JsonResponse
    {
        return $this->ok($query());
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
