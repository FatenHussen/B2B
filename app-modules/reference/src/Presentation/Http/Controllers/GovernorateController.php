<?php

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Presentation\Http\Requests\ChangeGovernorateStatusRequest;
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

    /**
     * EP-AD-043A. Replaces `destroy`, which hard deleted the row.
     *
     * CLAUDE.md rule 12: a reference entity is never hard deleted, disabling is logical
     * and audited. The catalog says the same in its own words — "المحافظات لا تُحذف،
     * تُعطَّل فقط" — and DOC-08 backs it by defining `ad.refs.disable` and no
     * `ad.refs.delete`. The route already carried the disable permission while the
     * controller still deleted; this closes that gap in the direction the contract names.
     *
     * `affected` reports what a disable would touch. Only `zones` is counted here:
     * retailers and channels live in Identity and Tenancy, and Reference is a Foundation
     * module that does not reach upward for them. EP-AD-034 wants those counts too and
     * that is settled in BE-R03, not by reaching across a boundary from here.
     */
    public function changeStatus(ChangeGovernorateStatusRequest $request, Governorate $governorate): JsonResponse
    {
        // Not `update()`: `status` is outside `$fillable` under rule 8, and mass
        // assignment would drop it silently and answer 200 having changed nothing.
        $governorate->status = RefStatus::from($request->validated('status'));
        $governorate->save();

        return $this->ok([
            'id' => $governorate->id,
            'status' => $governorate->status->value,
            'affected' => ['zones' => $governorate->zones()->count()],
        ]);
    }
}
