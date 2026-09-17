<?php

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\OpenOrderCounter;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Services\ReferenceMutations;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\Zone;
use Modules\Reference\Presentation\Http\Requests\ChangeZoneStatusRequest;
use Modules\Reference\Presentation\Http\Requests\StoreZoneRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateZoneRequest;
use Modules\Reference\Presentation\Http\Resources\ZoneResource;

/**
 * BE-R03 — EP-AD-032, 033, 042B, 034. Also the shared `GET /zones` reads.
 */
class ZoneController extends ApiController
{
    public function __construct(private readonly ReferenceMutations $mutations) {}

    /**
     * EP-AD-032. `filter[governorate_id]` per the catalog; the bare `governorate_id` the
     * shared read accepted before stays honoured so nothing already calling it breaks.
     *
     * The three usage counts per row come through Core contracts — Identity and Tenancy
     * own the tables — and are attached only on the platform path, where the dashboard
     * shows them. The shared read for the other guards carries none.
     */
    public function index(
        Request $request,
        RetailerDirectory $retailers,
        RepDirectory $reps,
        ChannelDirectory $channels,
    ): JsonResponse {
        $governorateId = $request->input('filter.governorate_id', $request->input('governorate_id'));
        $platform = $request->is('api/v1/platform/*');

        $rows = Zone::query()
            ->with('governorate')
            ->when($governorateId !== null && $governorateId !== '', fn ($q) => $q->where('governorate_id', (int) $governorateId))
            ->when($request->filled('filter.status'), function ($q) use ($request): void {
                $stored = ZoneStatus::fromContract((string) $request->input('filter.status'));
                $q->where('status', $stored !== null ? $stored->value : '__none__');
            })
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return $this->paginated($rows, function (Zone $zone) use ($platform, $retailers, $reps, $channels): array {
            $resource = new ZoneResource($zone);
            if ($platform) {
                $resource->withUsage([
                    'retailers_count' => $retailers->countInZone($zone->id),
                    'reps_count' => $reps->countInZone($zone->id),
                    'channels_count' => count($channels->activeIdsCoveringZone($zone->id)),
                ]);
            }

            return $resource->resolve();
        });
    }

    public function show(Zone $zone): JsonResponse
    {
        return $this->ok(new ZoneResource($zone->load('governorate')));
    }

    public function store(StoreZoneRequest $request): JsonResponse
    {
        $row = $this->mutations->create(
            ReferenceEntity::Zones,
            $request->safe()->except('reason'),
            $request->user(),
            $request->validated('reason'),
        );

        return $this->created(new ZoneResource($row));
    }

    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $row = $this->mutations->update(
            ReferenceEntity::Zones,
            $zone,
            $request->safe()->except('reason'),
            (string) $request->validated('reason'),
            $request->user(),
        );

        return $this->ok(new ZoneResource($row));
    }

    /**
     * EP-AD-034 — deliberately outside the 043 status family; BE-R03 calls that a
     * documented contract exception and ZoneTest pins it.
     *
     * The three impact counts come from contracts, never from a query here: retailers and
     * reps from Identity, open orders from Ordering. Counted live on every call — the
     * numbers justify an irreversible decision, so they are never cached or estimated.
     *
     * The request speaks the contract's vocabulary (`disabled`) and the column stores its
     * own (`inactive`); `ZoneStatus` translates at this boundary and nowhere else.
     */
    public function changeStatus(
        ChangeZoneStatusRequest $request,
        Zone $zone,
        RetailerDirectory $retailers,
        RepDirectory $reps,
        OpenOrderCounter $orders,
    ): JsonResponse {
        $to = ZoneStatus::fromContract((string) $request->validated('status')) ?? $zone->status;

        return $this->ok($this->mutations->changeStatus(
            ReferenceEntity::Zones,
            $zone,
            $zone->status->toContract(),
            $to->toContract(),
            function () use ($zone, $to): void {
                // Not `update()`: `status` is outside `$fillable` under rule 8.
                $zone->status = $to;
            },
            (string) $request->validated('reason'),
            $request->user(),
            [
                'retailers' => $retailers->countInZone($zone->id),
                'reps' => $reps->countInZone($zone->id),
                'open_orders' => $orders->countOpenInZone($zone->id),
            ],
        ));
    }
}
