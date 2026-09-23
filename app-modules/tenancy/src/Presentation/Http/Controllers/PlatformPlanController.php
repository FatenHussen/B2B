<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Tenancy\Application\Actions\CreatePlan;
use Modules\Tenancy\Application\Actions\UpdatePlan;
use Modules\Tenancy\Application\Queries\ShowPlan;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Presentation\Http\Requests\StorePlanRequest;
use Modules\Tenancy\Presentation\Http\Requests\UpdatePlanRequest;
use Modules\Tenancy\Presentation\Http\Resources\ChannelPlanResource;

/**
 * PA-02 — EP-AD-100A/B/C/D.
 */
final class PlatformPlanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $rows = ChannelPlan::query()
            ->orderBy('id')
            ->paginate(min(max((int) $request->integer('per_page', 25), 1), 100));

        return $this->paginated(
            $rows,
            fn (ChannelPlan $plan) => (new ChannelPlanResource($plan))->resolve(),
        );
    }

    public function store(StorePlanRequest $request, CreatePlan $action): JsonResponse
    {
        /** @var object $actor */
        $actor = $request->user();

        return $this->created($action($request->validated(), $actor));
    }

    public function show(ChannelPlan $plan, ShowPlan $query): JsonResponse
    {
        return $this->ok($query($plan));
    }

    public function update(UpdatePlanRequest $request, ChannelPlan $plan, UpdatePlan $action): JsonResponse
    {
        /** @var object $actor */
        $actor = $request->user();

        return $this->ok($action($plan, $request->validated(), $actor));
    }
}
