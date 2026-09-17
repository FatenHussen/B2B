<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Services\ReferenceMutations;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Presentation\Http\Requests\ChangeRefStatusRequest;
use Modules\Reference\Presentation\Http\Requests\StoreSaleUnitRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateSaleUnitRequest;
use Modules\Reference\Presentation\Http\Resources\SaleUnitResource;

/**
 * BE-R06 — EP-AD-037A, 037B, 042E, 043D.
 *
 * Unit conversion factors belong to the product in Layer 2; `default_factor` here is only
 * the suggestion a product starts from.
 */
final class SaleUnitController extends ApiController
{
    public function __construct(private readonly ReferenceMutations $mutations) {}

    public function index(Request $request): JsonResponse
    {
        $rows = SaleUnit::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where('name', 'like', $term)->orWhere('abbr', 'like', $term);
            }))
            ->when($request->filled('filter.status'), fn ($q) => $q->where('status', $request->input('filter.status')))
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return $this->paginated($rows, fn (SaleUnit $row) => (new SaleUnitResource($row))->resolve());
    }

    public function show(SaleUnit $saleUnit): JsonResponse
    {
        return $this->ok(new SaleUnitResource($saleUnit));
    }

    public function store(StoreSaleUnitRequest $request): JsonResponse
    {
        $row = $this->mutations->create(
            ReferenceEntity::SaleUnits,
            $request->safe()->except('reason'),
            $request->user(),
            $request->validated('reason'),
        );

        return $this->created(new SaleUnitResource($row));
    }

    public function update(UpdateSaleUnitRequest $request, SaleUnit $saleUnit): JsonResponse
    {
        $row = $this->mutations->update(
            ReferenceEntity::SaleUnits,
            $saleUnit,
            $request->safe()->except('reason'),
            (string) $request->validated('reason'),
            $request->user(),
        );

        return $this->ok(new SaleUnitResource($row));
    }

    /**
     * EP-AD-043D — "لا تُحذف وحدة مستخدَمة في أي منتج": a unit any product sells in
     * refuses to be disabled, `ref_in_use` with the product count. The count comes from
     * Catalog through a Core contract.
     */
    public function changeStatus(
        ChangeRefStatusRequest $request,
        SaleUnit $saleUnit,
        CatalogProductLookup $catalog,
    ): JsonResponse {
        $to = RefStatus::from($request->validated('status'));

        return $this->ok($this->mutations->changeStatus(
            ReferenceEntity::SaleUnits,
            $saleUnit,
            $saleUnit->status->value,
            $to->value,
            function () use ($saleUnit, $to): void {
                $saleUnit->status = $to;
            },
            (string) $request->validated('reason'),
            $request->user(),
            [],
            ['products' => $catalog->countProductsUsingSaleUnit($saleUnit->id)],
        ));
    }
}
