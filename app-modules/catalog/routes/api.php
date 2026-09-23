<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Catalog\Presentation\Http\Controllers\ChannelCatalogController;
use Modules\Catalog\Presentation\Http\Controllers\RepCatalogController;
use Modules\Catalog\Presentation\Http\Controllers\RetailerCatalogController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel')
    ->group(function (): void {
        Route::get('brands', [ChannelCatalogController::class, 'brands'])->middleware('permission:sc.catalog.view');
        Route::post('brands', [ChannelCatalogController::class, 'storeBrand'])->middleware('permission:sc.catalog.create');
        Route::get('categories/tree', [ChannelCatalogController::class, 'categoryTree'])->middleware('permission:sc.catalog.view');
        Route::post('categories', [ChannelCatalogController::class, 'storeCategory'])->middleware('permission:sc.catalog.create');
        Route::post('categories/reorder', [ChannelCatalogController::class, 'reorderCategories'])->middleware('permission:sc.catalog.update');
        Route::get('products', [ChannelCatalogController::class, 'products'])->middleware('permission:sc.catalog.view');
        Route::get('products/{id}', [ChannelCatalogController::class, 'showProduct'])->middleware('permission:sc.catalog.view');
        Route::post('products', [ChannelCatalogController::class, 'storeProduct'])->middleware('permission:sc.catalog.create');
        Route::put('products/{id}', [ChannelCatalogController::class, 'updateProduct'])->middleware('permission:sc.catalog.update');
        Route::post('products/{id}/variants/generate', [ChannelCatalogController::class, 'generateVariants'])->middleware('permission:sc.catalog.variants');
        Route::post('products/bulk', [ChannelCatalogController::class, 'bulk'])->middleware('permission:sc.catalog.update');
        Route::post('catalog/import', [ChannelCatalogController::class, 'import'])->middleware('permission:sc.catalog.import');
        Route::get('catalog/export', [ChannelCatalogController::class, 'export'])->middleware('permission:sc.catalog.view');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:retailer'])
    ->prefix('api/v1/app/retailer')
    ->group(function (): void {
        Route::get('home', [RetailerCatalogController::class, 'home']);
        Route::get('categories', [RetailerCatalogController::class, 'categories']);
        Route::get('products', [RetailerCatalogController::class, 'products']);
        Route::get('products/{id}', [RetailerCatalogController::class, 'showProduct']);
        Route::post('products/{id}/favorite', [RetailerCatalogController::class, 'favorite']);
        Route::get('brands', [RetailerCatalogController::class, 'brands']);
        Route::get('brands/{id}', [RetailerCatalogController::class, 'showBrand']);
        Route::get('shortages', [RetailerCatalogController::class, 'shortages']);
        Route::post('shortages', [RetailerCatalogController::class, 'storeShortage']);
        Route::delete('shortages/{id}', [RetailerCatalogController::class, 'deleteShortage']);
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:rep'])
    ->prefix('api/v1/app/rep')
    ->group(function (): void {
        Route::get('products', [RepCatalogController::class, 'products']);
        Route::get('products/{id}', [RepCatalogController::class, 'show']);
    });
