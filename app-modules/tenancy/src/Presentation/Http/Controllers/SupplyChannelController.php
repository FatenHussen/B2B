<?php

namespace Modules\Tenancy\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Tenancy\Application\Actions\CreateChannel;
use Modules\Tenancy\Application\Actions\OverrideChannelLimits;
use Modules\Tenancy\Application\Actions\RetryProvisioning;
use Modules\Tenancy\Application\Actions\TransitionChannel;
use Modules\Tenancy\Application\Actions\UpdateChannel;
use Modules\Tenancy\Application\Queries\ChannelUsage;
use Modules\Tenancy\Application\Queries\ShowChannel;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Presentation\Http\Requests\OverrideChannelLimitsRequest;
use Modules\Tenancy\Presentation\Http\Requests\StoreSupplyChannelRequest;
use Modules\Tenancy\Presentation\Http\Requests\TransitionChannelRequest;
use Modules\Tenancy\Presentation\Http\Requests\UpdateSupplyChannelRequest;
use Modules\Tenancy\Presentation\Http\Resources\SupplyChannelResource;

class SupplyChannelController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->paginated(
            SupplyChannel::query()->orderBy('name')->paginate(),
            fn (SupplyChannel $channel) => (new SupplyChannelResource($channel))->resolve(),
        );
    }

    /**
     * EP-AD-052. Nested detail: channel (with allowed_next), plan, limits, coverage,
     * genuine KPI zeros, timeline, notes. Manager is null until BE-T07.
     */
    public function show(SupplyChannel $supplyChannel, ShowChannel $query): JsonResponse
    {
        return $this->ok($query($supplyChannel));
    }

    /**
     * EP-AD-051. Answers `id`, `status` and `provisioning_job_id` — under 500ms, still
     * in `provisioning`. Does not wait for `active` (BE-T05).
     */
    public function store(StoreSupplyChannelRequest $request, CreateChannel $action): JsonResponse
    {
        return $this->created($action(
            $request->validated(),
            $request->user(),
        ));
    }

    /**
     * EP-AD-062. Answers `{id}`. Reason is required and audited.
     */
    public function update(UpdateSupplyChannelRequest $request, SupplyChannel $supplyChannel, UpdateChannel $action): JsonResponse
    {
        /** @var object $actor */
        $actor = $request->user();

        return $this->ok($action($supplyChannel, $request->validated(), $actor));
    }

    public function destroy(SupplyChannel $supplyChannel): JsonResponse
    {
        $supplyChannel->delete();

        return $this->noContent();
    }

    /**
     * EP-AD-053. Returns `{job_id}` and is safe to call repeatedly.
     */
    public function retryProvisioning(SupplyChannel $supplyChannel, RetryProvisioning $action): JsonResponse
    {
        return $this->ok($action($supplyChannel));
    }

    /**
     * EP-AD-054. Answers `status` and `allowed_next`, as the catalog's `r` says — the
     * client draws its next buttons from this response.
     */
    public function transition(TransitionChannelRequest $request, SupplyChannel $supplyChannel, TransitionChannel $action): JsonResponse
    {
        /** @var object $actor */
        $actor = $request->user();

        return $this->ok($action(
            $supplyChannel,
            ChannelStatus::from((string) $request->validated('to_status')),
            $actor,
            (string) $request->validated('reason'),
        ));
    }

    /**
     * EP-AD-055 (BE-T12). Overrides one or more plan limits with a mandatory reason and
     * an optional expiry; answers the effective limits after the change.
     */
    public function overrideLimits(OverrideChannelLimitsRequest $request, SupplyChannel $supplyChannel, OverrideChannelLimits $action): JsonResponse
    {
        /** @var object $actor */
        $actor = $request->user();

        return $this->ok($action($supplyChannel, $request->validated(), $actor));
    }

    /**
     * EP-AD-056 (BE-T11). The 30-day series and plan-limit usage from real counters.
     */
    public function usage(Request $request, SupplyChannel $supplyChannel, ChannelUsage $query): JsonResponse
    {
        return $this->ok($query($supplyChannel, (string) $request->query('range', '30d')));
    }
}
