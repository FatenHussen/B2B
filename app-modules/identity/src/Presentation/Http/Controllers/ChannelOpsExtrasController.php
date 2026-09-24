<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Contracts\ChannelUserDirectory;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepLiveLocation;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Http\ApiController;
use Modules\Core\Support\Tenant;
use Modules\Identity\Application\Actions\InviteChannelUser;
use Modules\Identity\Application\Queries\ListChannelRepsLive;
use Modules\Identity\Application\Queries\ShowChannelRetailer;
use Modules\Identity\Application\Queries\ShowChannelZoneCoverage;
use Modules\Identity\Presentation\Http\Requests\InviteChannelUserRequest;

final class ChannelOpsExtrasController extends ApiController
{
    public function showRetailer(ShowChannelRetailer $query, int $id): JsonResponse
    {
        return $this->ok($query($id));
    }

    public function repsLive(ListChannelRepsLive $query): JsonResponse
    {
        return $this->ok($query());
    }

    public function repLive(
        RepDirectory $reps,
        RepLiveLocation $live,
        int $id,
    ): JsonResponse {
        $channelId = (int) Tenant::currentId();
        if (! $reps->belongsToChannel($id, $channelId)) {
            throw new DomainException(__('identity.not_found'), 'not_found', 404);
        }

        return $this->ok($live->latest($id) ?? [
            'lat' => null,
            'lng' => null,
            'at' => null,
            'on_duty' => false,
        ]);
    }

    public function zoneCoverage(ShowChannelZoneCoverage $query): JsonResponse
    {
        return $this->ok($query());
    }

    public function users(ChannelUserDirectory $directory): JsonResponse
    {
        return $this->ok($directory->listForChannel((int) Tenant::currentId()));
    }

    public function invite(InviteChannelUserRequest $request, InviteChannelUser $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }
}
