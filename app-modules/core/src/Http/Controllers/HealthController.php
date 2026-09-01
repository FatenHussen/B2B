<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiResponse;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'app' => config('app.name'),
            'env' => config('app.env'),
        ]);
    }
}
