<?php

namespace Modules\Tenancy\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Presentation\Http\Requests\StoreSupplyChannelRequest;
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

    public function store(StoreSupplyChannelRequest $request): JsonResponse
    {
        $channel = SupplyChannel::create($request->validated());

        return $this->created(new SupplyChannelResource($channel));
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
}
