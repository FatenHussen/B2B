<?php

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Services\ReferenceMutations;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Presentation\Http\Requests\ChangeGovernorateStatusRequest;
use Modules\Reference\Presentation\Http\Requests\StoreGovernorateRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateGovernorateRequest;
use Modules\Reference\Presentation\Http\Resources\GovernorateResource;

/**
 * BE-R02 — EP-AD-030, 031, 042A, 043A. Also the shared `GET /governorates` reads.
 */
class GovernorateController extends ApiController
{
    public function __construct(private readonly ReferenceMutations $mutations) {}

    public function index(Request $request): JsonResponse
    {
        $rows = Governorate::query()
            ->withCount('zones')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('code', 'like', $term);
            }))
            ->when($request->filled('filter.status'), fn ($q) => $q->where('status', $request->input('filter.status')))
            ->orderBy('order')
            ->orderBy('name_ar')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return $this->paginated($rows, fn (Governorate $row) => (new GovernorateResource($row))->resolve());
    }

    public function show(Governorate $governorate): JsonResponse
    {
        return $this->ok(new GovernorateResource($governorate->loadCount('zones')));
    }

    public function store(StoreGovernorateRequest $request): JsonResponse
    {
        $row = $this->mutations->create(
            ReferenceEntity::Governorates,
            $request->safe()->except('reason'),
            $request->user(),
            $request->validated('reason'),
        );

        return $this->created(new GovernorateResource($row));
    }

    public function update(UpdateGovernorateRequest $request, Governorate $governorate): JsonResponse
    {
        $row = $this->mutations->update(
            ReferenceEntity::Governorates,
            $governorate,
            $request->safe()->except('reason'),
            (string) $request->validated('reason'),
            $request->user(),
        );

        return $this->ok(new GovernorateResource($row));
    }

    /**
     * EP-AD-043A. Disable, never delete — rule 12 and "المحافظات لا تُحذف، تُعطَّل فقط".
     *
     * `affected` names what the change touches: zones from this module, retailers from
     * Identity and channels from Tenancy, each a number through a Core contract so
     * Reference reads no other module's table. Counted live: the numbers justify an
     * irreversible decision.
     */
    public function changeStatus(
        ChangeGovernorateStatusRequest $request,
        Governorate $governorate,
        RetailerDirectory $retailers,
        ChannelDirectory $channels,
    ): JsonResponse {
        $to = RefStatus::from($request->validated('status'));

        return $this->ok($this->mutations->changeStatus(
            ReferenceEntity::Governorates,
            $governorate,
            $governorate->status->value,
            $to->value,
            function () use ($governorate, $to): void {
                // Not `update()`: `status` is outside `$fillable` under rule 8.
                $governorate->status = $to;
            },
            (string) $request->validated('reason'),
            $request->user(),
            [
                'zones' => $governorate->zones()->count(),
                'retailers' => $retailers->countInGovernorate($governorate->id),
                'channels' => $channels->countCoveringGovernorate($governorate->id),
            ],
        ));
    }
}
