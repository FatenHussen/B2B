<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Application\Queries\ListRepProducts;
use Modules\Catalog\Application\Queries\ShowRepProduct;
use Modules\Core\Http\ApiController;

final class RepCatalogController extends ApiController
{
    public function products(Request $request, ListRepProducts $query): JsonResponse
    {
        return $this->paginated(
            $query($request->user(), $request),
            fn ($product) => $query->map($request->user(), $product),
        );
    }

    public function show(Request $request, ShowRepProduct $query, int $id): JsonResponse
    {
        return $this->ok($query($request->user(), $request, $id));
    }
}
