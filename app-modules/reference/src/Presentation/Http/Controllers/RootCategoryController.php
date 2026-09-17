<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Services\ReferenceMutations;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Presentation\Http\Requests\ChangeRefStatusRequest;
use Modules\Reference\Presentation\Http\Requests\StoreRootCategoryRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateRootCategoryRequest;
use Modules\Reference\Presentation\Http\Resources\RootCategoryResource;

/**
 * BE-R05 — EP-AD-036A, 036B, 042D, 043C.
 *
 * Root categories are platform-level; the channel tree in Catalog hangs off them. That is
 * why disabling one is refused while anything still hangs there (AC-AD-11).
 */
final class RootCategoryController extends ApiController
{
    public function __construct(private readonly ReferenceMutations $mutations) {}

    public function index(Request $request): JsonResponse
    {
        $rows = RootCategory::query()
            ->with('activityTypes')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('filter.status'), fn ($q) => $q->where('status', $request->input('filter.status')))
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return $this->paginated($rows, fn (RootCategory $row) => (new RootCategoryResource($row))->resolve());
    }

    public function show(RootCategory $rootCategory): JsonResponse
    {
        return $this->ok(new RootCategoryResource($rootCategory->load('activityTypes')));
    }

    public function store(StoreRootCategoryRequest $request): JsonResponse
    {
        $row = DB::transaction(function () use ($request): RootCategory {
            /** @var RootCategory $row */
            $row = $this->mutations->create(
                ReferenceEntity::RootCategories,
                $request->safe()->except(['reason', 'activity_type_ids']),
                $request->user(),
                $request->validated('reason'),
            );
            $row->activityTypes()->sync($request->validated('activity_type_ids') ?? []);

            return $row;
        });

        return $this->created(new RootCategoryResource($row->load('activityTypes')));
    }

    public function update(UpdateRootCategoryRequest $request, RootCategory $rootCategory): JsonResponse
    {
        $row = DB::transaction(function () use ($request, $rootCategory): RootCategory {
            /** @var RootCategory $row */
            $row = $this->mutations->update(
                ReferenceEntity::RootCategories,
                $rootCategory,
                $request->safe()->except(['reason', 'activity_type_ids']),
                (string) $request->validated('reason'),
                $request->user(),
            );
            if ($request->has('activity_type_ids')) {
                $row->activityTypes()->sync($request->validated('activity_type_ids'));
            }

            return $row;
        });

        return $this->ok(new RootCategoryResource($row->load('activityTypes')));
    }

    /**
     * EP-AD-043C. Refuses to disable while channel categories or active products still
     * hang off the root, with the counts that say why (`ref_in_use`). Both numbers come
     * from Catalog through a Core contract; Reference reads no catalog table.
     *
     * The status is 422, not the 409 the catalog example shows: CLAUDE.md's status map and
     * `ErrorCode::RefInUse` put `ref_in_use` at 422, and one renderer decides — a code
     * cannot reach a client under two statuses depending on who threw it.
     */
    public function changeStatus(
        ChangeRefStatusRequest $request,
        RootCategory $rootCategory,
        CatalogProductLookup $catalog,
        RetailerDirectory $retailers,
    ): JsonResponse {
        $to = RefStatus::from($request->validated('status'));

        return $this->ok($this->mutations->changeStatus(
            ReferenceEntity::RootCategories,
            $rootCategory,
            $rootCategory->status->value,
            $to->value,
            function () use ($rootCategory, $to): void {
                $rootCategory->status = $to;
            },
            (string) $request->validated('reason'),
            $request->user(),
            ['retailers' => $retailers->countByRootCategory($rootCategory->id)],
            [
                'child_categories' => $catalog->countCategoriesUnderRoot($rootCategory->id),
                'active_products' => $catalog->countActiveProductsUnderRoot($rootCategory->id),
            ],
        ));
    }
}
