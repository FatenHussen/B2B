<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Queries\PublicReferenceSnapshot;

/**
 * BE-R10 — EP-PB-001 `GET /public/refs`. No Bearer, no idempotency key, no channel.
 */
final class PublicRefsController extends ApiController
{
    public function index(Request $request, PublicReferenceSnapshot $snapshot): JsonResponse
    {
        $payload = $snapshot($request->query('since'));

        // The cursor rides in `meta` as the envelope promises (`meta.sync_cursor`) and
        // stays in `data` too, where the catalog example put it. Both are the same value.
        return $this->ok($payload, ['sync_cursor' => $payload['sync_cursor']]);
    }
}
