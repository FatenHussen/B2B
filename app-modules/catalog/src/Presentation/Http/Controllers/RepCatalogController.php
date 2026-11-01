<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Application\Queries\ListRepBrands;
use Modules\Catalog\Application\Queries\ListRepCategories;
use Modules\Catalog\Application\Queries\ListRepProducts;
use Modules\Catalog\Application\Queries\ShowRepProduct;
use Modules\Core\Http\ApiController;

final class RepCatalogController extends ApiController
{
    public function categories(Request $request, ListRepCategories $query): JsonResponse
    {
        $root = $request->query('root_category_id');

        return $this->ok($query($request->user(), is_numeric($root) ? (int) $root : null));
    }

    public function brands(Request $request, ListRepBrands $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }

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
