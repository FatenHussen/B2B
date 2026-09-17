<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Services\ReferenceMutations;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Presentation\Http\Requests\ChangeRefStatusRequest;
use Modules\Reference\Presentation\Http\Requests\StoreEquipmentRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateEquipmentRequest;
use Modules\Reference\Presentation\Http\Resources\EquipmentResource;

/**
 * BE-R07 — EP-AD-038A, 038B, 042F, 043E. The shared reference pattern with nothing
 * bespoke: that is the ticket's one acceptance criterion.
 */
final class EquipmentController extends ApiController
{
    public function __construct(private readonly ReferenceMutations $mutations) {}

    public function index(Request $request): JsonResponse
    {
        $rows = Equipment::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('filter.status'), fn ($q) => $q->where('status', $request->input('filter.status')))
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return $this->paginated($rows, fn (Equipment $row) => (new EquipmentResource($row))->resolve());
    }

    public function show(Equipment $equipment): JsonResponse
    {
        return $this->ok(new EquipmentResource($equipment));
    }

    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $row = $this->mutations->create(
            ReferenceEntity::Equipments,
            $request->safe()->except('reason'),
            $request->user(),
            $request->validated('reason'),
        );

        return $this->created(new EquipmentResource($row));
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): JsonResponse
    {
        $row = $this->mutations->update(
            ReferenceEntity::Equipments,
            $equipment,
            $request->safe()->except('reason'),
            (string) $request->validated('reason'),
            $request->user(),
        );

        return $this->ok(new EquipmentResource($row));
    }

    /** EP-AD-043E. `affected.retailers` through the Identity contract. */
    public function changeStatus(
        ChangeRefStatusRequest $request,
        Equipment $equipment,
        RetailerDirectory $retailers,
    ): JsonResponse {
        $to = RefStatus::from($request->validated('status'));

        return $this->ok($this->mutations->changeStatus(
            ReferenceEntity::Equipments,
            $equipment,
            $equipment->status->value,
            $to->value,
            function () use ($equipment, $to): void {
                $equipment->status = $to;
            },
            (string) $request->validated('reason'),
            $request->user(),
            ['retailers' => $retailers->countByEquipment($equipment->id)],
        ));
    }
}
