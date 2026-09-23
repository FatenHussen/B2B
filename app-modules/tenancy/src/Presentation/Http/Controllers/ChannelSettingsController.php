<?php

namespace Modules\Tenancy\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Application\Queries\ListChannelWarehouses;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Presentation\Http\Requests\UpdateOwnChannelRequest;
use Modules\Tenancy\Presentation\Http\Resources\SupplyChannelResource;

class ChannelSettingsController extends ApiController
{
    public function show(): JsonResponse
    {
        return $this->ok(new SupplyChannelResource($this->currentChannel()));
    }

    public function update(UpdateOwnChannelRequest $request): JsonResponse
    {
        $channel = $this->currentChannel();
        $channel->update($request->validated());

        return $this->ok(new SupplyChannelResource($channel));
    }

    public function warehouses(ListChannelWarehouses $query): JsonResponse
    {
        return $this->ok($query());
    }

    private function currentChannel(): SupplyChannel
    {
        abort_unless(Tenant::check(), 404);

        return SupplyChannel::query()->findOrFail(Tenant::currentId());
    }
}
