<?php

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\OpenOrderCounter;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Http\ApiController;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\Zone;
use Modules\Reference\Presentation\Http\Requests\ChangeZoneStatusRequest;
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

    /**
     * EP-AD-034. Replaces `destroy`, which hard deleted the row.
     *
     * Deliberately outside the 043 status family — BE-R03 calls that a documented
     * contract exception, and the route test pins it so it cannot drift.
     *
     * The three impact counts come from contracts, never from a query here: retailers and
     * reps from Identity, open orders from Ordering. Reference is a Foundation module and
     * does not read their tables. This controller sits in `Presentation`, which deptrac
     * permits to depend on Coordination and Foundation alike, so the imports below are
     * the sanctioned direction rather than Foundation reaching upward.
     *
     * Counted live on every call, per BE-R03: the numbers justify an irreversible
     * decision, so they are never cached and never estimated.
     */
    public function changeStatus(
        ChangeZoneStatusRequest $request,
        Zone $zone,
        RetailerDirectory $retailers,
        RepDirectory $reps,
        OpenOrderCounter $orders,
    ): JsonResponse {
        // `fromContract`, not `from`: the request speaks the contract's vocabulary and the
        // column stores its own. Validation already rejected anything else, so this
        // cannot be null — the ?? is a guard against a future case added to one side only.
        $zone->status = ZoneStatus::fromContract($request->validated('status')) ?? $zone->status;

        // Not `update()`: `status` is outside `$fillable` under rule 8 and mass assignment
        // would drop it silently, answering 200 having changed nothing.
        $zone->save();

        return $this->ok([
            'id' => $zone->id,
            'status' => $zone->status->toContract(),
            'affected' => [
                'retailers' => $retailers->countInZone($zone->id),
                'reps' => $reps->countInZone($zone->id),
                'open_orders' => $orders->countOpenInZone($zone->id),
            ],
        ]);
    }
}
