<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Content\Application\Actions\UpdatePlatformIntro;
use Modules\Content\Application\Queries\ShowPlatformIntro;
use Modules\Content\Presentation\Http\Requests\UpdateIntroRequest;
use Modules\Core\Http\ApiController;

final class PlatformContentController extends ApiController
{
    public function showIntro(ShowPlatformIntro $query): JsonResponse
    {
        return $this->ok($query());
    }

    public function updateIntro(UpdateIntroRequest $request, UpdatePlatformIntro $action): JsonResponse
    {
        return $this->ok($action($request->validated()));
    }
}
