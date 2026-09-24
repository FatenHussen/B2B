<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Content\Application\Actions\RecordBannerClick;
use Modules\Content\Application\Queries\ListHomeBlocks;
use Modules\Core\Http\ApiController;

final class AppContentController extends ApiController
{
    public function homeBlocks(Request $request, ListHomeBlocks $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }

    public function bannerClick(Request $request, RecordBannerClick $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id));
    }
}
