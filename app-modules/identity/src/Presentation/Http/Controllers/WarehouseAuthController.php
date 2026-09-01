<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\DeviceLogin;
use Modules\Identity\Presentation\Http\Requests\DeviceLoginRequest;

final class WarehouseAuthController extends ApiController
{
    public function deviceLogin(DeviceLoginRequest $request, DeviceLogin $action): JsonResponse
    {
        return $this->ok($action(
            $request->string('device_token')->toString(),
            $request->string('pin')->toString(),
        ));
    }
}
