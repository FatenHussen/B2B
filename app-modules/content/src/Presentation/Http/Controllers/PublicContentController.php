<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Content\Application\Queries\ShowPlatformIntro;
use Modules\Core\Http\ApiController;

final class PublicContentController extends ApiController
{
    /**
     * EP-PB-011 — first-run splash for the retailer and rep apps. Same row the
     * platform back office writes at GET/PUT /platform/content/intro.
     */
    public function showIntro(ShowPlatformIntro $query): JsonResponse
    {
        return $this->ok($query());
    }
}
