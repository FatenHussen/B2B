<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Actions\PostFxRate;
use Modules\Reference\Domain\Models\FxRate;
use Modules\Reference\Presentation\Http\Requests\StoreFxRateRequest;
use Modules\Reference\Presentation\Http\Resources\FxRateResource;

/**
 * BE-R09 — EP-AD-040 (post) and EP-AD-043G (list). Both behind `ad.refs.currency`.
 */
final class FxRateController extends ApiController
{
    /** EP-AD-043G. `filter[currency_id]` narrows to one currency's history. */
    public function index(Request $request): JsonResponse
    {
        $rows = FxRate::query()
            ->when($request->filled('filter.currency_id'), fn ($q) => $q->where('from_currency_id', (int) $request->input('filter.currency_id')))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return $this->paginated($rows, fn (FxRate $row) => (new FxRateResource($row))->resolve());
    }

    /**
     * EP-AD-040. Does not reprice any open order (BR-AD-19): orders freeze their currency
     * and rate at submission, and nothing here reaches them.
     */
    public function store(StoreFxRateRequest $request, PostFxRate $action): JsonResponse
    {
        return $this->created(new FxRateResource($action($request->validated(), $request->user())));
    }
}
