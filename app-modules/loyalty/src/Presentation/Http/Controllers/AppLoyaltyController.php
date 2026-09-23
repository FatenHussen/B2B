<?php

declare(strict_types=1);

namespace Modules\Loyalty\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Loyalty\Application\Actions\RedeemLoyaltyReward;
use Modules\Loyalty\Application\Queries\ShowAppLoyalty;
use Modules\Loyalty\Presentation\Http\Requests\RedeemLoyaltyRewardRequest;

final class AppLoyaltyController extends ApiController
{
    public function show(Request $request, ShowAppLoyalty $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }

    public function redeem(RedeemLoyaltyRewardRequest $request, RedeemLoyaltyReward $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }
}
