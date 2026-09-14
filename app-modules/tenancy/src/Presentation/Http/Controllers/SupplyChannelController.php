<?php

namespace Modules\Tenancy\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Tenancy\Application\Actions\CreateChannel;
use Modules\Tenancy\Application\Actions\TransitionChannel;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
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

    public function show(SupplyChannel $supplyChannel): JsonResponse
    {
        return $this->ok(new SupplyChannelResource($supplyChannel));
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

    public function update(UpdateSupplyChannelRequest $request, SupplyChannel $supplyChannel): JsonResponse
    {
        $supplyChannel->update($request->validated());

        return $this->ok(new SupplyChannelResource($supplyChannel));
    }

    public function destroy(SupplyChannel $supplyChannel): JsonResponse
    {
        $supplyChannel->delete();

        return $this->noContent();
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
}
