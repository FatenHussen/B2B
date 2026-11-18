<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Application\Actions\AddShortage;
use Modules\Catalog\Application\Actions\DeleteShortage;
use Modules\Catalog\Application\Actions\ToggleFavorite;
use Modules\Catalog\Application\Queries\ListRetailerCategories;
use Modules\Catalog\Application\Queries\ListRetailerProducts;
use Modules\Catalog\Application\Queries\ListShortages;
use Modules\Catalog\Application\Queries\RetailerBrands;
use Modules\Catalog\Application\Queries\RetailerHome;
use Modules\Catalog\Application\Queries\ShowRetailerProduct;
use Modules\Catalog\Presentation\Http\Requests\StoreShortageRequest;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Http\ApiController;

final class RetailerCatalogController extends ApiController
{
    public function home(Request $request, RetailerHome $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }

    public function categories(Request $request, ListRetailerCategories $query): JsonResponse
    {
        return $this->ok($query($request->user(), $request));
    }

    public function products(Request $request, ListRetailerProducts $query): JsonResponse
    {
        $page = $query->paginate($request->user(), $request);

        return $this->paginated($page, fn ($product) => $query->cardFor($request->user(), $product));
    }

    public function showProduct(Request $request, ShowRetailerProduct $query, int $id): JsonResponse
    {
        return $this->ok($query($request->user(), $id));
    }

    public function favorite(Request $request, ToggleFavorite $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id));
    }

    public function favorites(Request $request, ListRetailerProducts $query): JsonResponse
    {
        $page = $query->favorites($request->user(), $request);

        return $this->paginated($page, fn ($product) => $query->cardFor($request->user(), $product));
    }

    public function brands(Request $request, RetailerBrands $query, RetailerShoppingContext $shopping): JsonResponse
    {
        $user = $request->user();
        $ctx = $shopping->for($user);

        return $this->paginated(
            $query->paginate($user, $request),
            fn ($brand) => $query->mapList($brand, $ctx),
        );
    }

    public function showBrand(Request $request, RetailerBrands $query, int $id): JsonResponse
    {
        return $this->ok($query->show($request->user(), $id));
    }

    public function shortages(Request $request, ListShortages $query): JsonResponse
    {
        return $this->paginated($query($request->user(), $request), fn ($row) => $query->map($row));
    }

    public function storeShortage(StoreShortageRequest $request, AddShortage $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function deleteShortage(Request $request, DeleteShortage $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id));
    }
}
