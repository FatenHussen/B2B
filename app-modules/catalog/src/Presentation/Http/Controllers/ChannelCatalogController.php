<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Application\Actions\BulkUpdateProducts;
use Modules\Catalog\Application\Actions\CreateBrand;
use Modules\Catalog\Application\Actions\CreateCategory;
use Modules\Catalog\Application\Actions\ExportCatalog;
use Modules\Catalog\Application\Actions\GenerateVariants;
use Modules\Catalog\Application\Actions\ImportCatalog;
use Modules\Catalog\Application\Actions\ReorderCategories;
use Modules\Catalog\Application\Actions\SaveProduct;
use Modules\Catalog\Application\Queries\CategoryTree;
use Modules\Catalog\Application\Queries\ListBrands;
use Modules\Catalog\Application\Queries\ListChannelProducts;
use Modules\Catalog\Application\Queries\ShowChannelProduct;
use Modules\Catalog\Presentation\Http\Requests\BulkProductsRequest;
use Modules\Catalog\Presentation\Http\Requests\GenerateVariantsRequest;
use Modules\Catalog\Presentation\Http\Requests\ImportCatalogRequest;
use Modules\Catalog\Presentation\Http\Requests\ReorderCategoriesRequest;
use Modules\Catalog\Presentation\Http\Requests\StoreBrandRequest;
use Modules\Catalog\Presentation\Http\Requests\StoreCategoryRequest;
use Modules\Catalog\Presentation\Http\Requests\StoreProductRequest;
use Modules\Core\Http\ApiController;

final class ChannelCatalogController extends ApiController
{
    public function brands(Request $request, ListBrands $query): JsonResponse
    {
        return $this->paginated($query($request), fn ($brand) => [
            'id' => (int) $brand->id,
            'name_ar' => $brand->name_ar,
            'name_en' => $brand->name_en,
            'status' => $brand->status->value,
        ]);
    }

    public function storeBrand(StoreBrandRequest $request, CreateBrand $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function categoryTree(CategoryTree $query): JsonResponse
    {
        return $this->ok($query());
    }

    public function storeCategory(StoreCategoryRequest $request, CreateCategory $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function reorderCategories(ReorderCategoriesRequest $request, ReorderCategories $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function products(Request $request, ListChannelProducts $query): JsonResponse
    {
        return $this->paginated($query($request), fn ($p) => [
            'id' => (int) $p->id,
            'sku' => $p->sku,
            'name_ar' => $p->name_ar,
            'status' => $p->status->value,
        ]);
    }

    public function showProduct(ShowChannelProduct $query, int $id): JsonResponse
    {
        return $this->ok($query($id));
    }

    public function storeProduct(StoreProductRequest $request, SaveProduct $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function updateProduct(StoreProductRequest $request, SaveProduct $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated(), $id));
    }

    public function generateVariants(GenerateVariantsRequest $request, GenerateVariants $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function bulk(BulkProductsRequest $request, BulkUpdateProducts $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function import(ImportCatalogRequest $request, ImportCatalog $action): JsonResponse
    {
        return $this->ok($action(
            $request->user(),
            $request->file('file'),
            $request->boolean('dry_run'),
        ));
    }

    public function export(Request $request, ExportCatalog $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->input('filter.status')));
    }
}
