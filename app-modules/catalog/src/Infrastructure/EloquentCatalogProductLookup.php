<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure;

use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductMedia;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\MediaUrl;
use Modules\Core\Support\Tenant;

final class EloquentCatalogProductLookup implements CatalogProductLookup
{
    public function __construct(private readonly ReferenceDirectory $refs) {}

    public function exists(int $productId): bool
    {
        return Tenant::withoutScope(fn () => Product::query()->whereKey($productId)->exists());
    }

    public function isActive(int $productId): bool
    {
        return Tenant::withoutScope(fn () => Product::query()
            ->whereKey($productId)
            ->where('status', ProductStatus::Active)
            ->exists());
    }

    public function channelId(int $productId): ?int
    {
        $id = Tenant::withoutScope(fn () => Product::query()->whereKey($productId)->value('supply_channel_id'));

        return $id !== null ? (int) $id : null;
    }

    public function name(int $productId): ?string
    {
        $name = Tenant::withoutScope(fn () => Product::query()->whereKey($productId)->value('name_ar'));

        return is_string($name) ? $name : null;
    }

    public function saleUnitName(int $productId): ?string
    {
        $unitId = Tenant::withoutScope(fn () => Product::query()->whereKey($productId)->value('sale_unit_id'));

        return $unitId !== null ? $this->refs->saleUnitName((int) $unitId) : null;
    }

    public function isVisibleToRetailer(int $productId, array $shopping): bool
    {
        $channelIds = $shopping['channel_ids'] ?? [];
        if ($channelIds === []) {
            return false;
        }

        return Tenant::withoutScope(fn () => Product::query()
            ->whereKey($productId)
            ->where('status', ProductStatus::Active)
            ->whereIn('supply_channel_id', $channelIds)
            ->whereHas('zones', fn ($q) => $q->where('zone_id', $shopping['zone_id']))
            ->whereHas('activityTypes', fn ($q) => $q->where('activity_type_id', $shopping['activity_type_id']))
            ->exists());
    }

    public function snapshot(int $productId, ?int $variantId = null): ?array
    {
        return Tenant::withoutScope(function () use ($productId, $variantId): ?array {
            $product = Product::query()->with('brand')->whereKey($productId)->first();
            if ($product === null) {
                return null;
            }

            $barcode = is_string($product->barcode) ? $product->barcode : null;
            if ($variantId !== null && $variantId > 0) {
                $variant = ProductVariant::query()->where('product_id', $productId)->whereKey($variantId)->first();
                if ($variant !== null && is_string($variant->barcode) && $variant->barcode !== '') {
                    $barcode = $variant->barcode;
                }
            }

            $primary = ProductMedia::query()
                ->where('product_id', $productId)
                ->orderByRaw("CASE WHEN role = 'primary' THEN 0 ELSE 1 END")
                ->orderBy('order')
                ->first();

            return [
                'id' => (int) $product->id,
                'name' => (string) $product->name_ar,
                'sku' => (string) $product->sku,
                'barcode' => $barcode,
                'brand' => $product->brand?->name_ar,
                'channel_id' => (int) $product->supply_channel_id,
                'tracked' => (bool) $product->tracked,
                'reorder_point' => (int) ($product->reorder_point ?? 0),
                'min_order_qty' => (int) ($product->min_order_qty ?? 1),
                'sale_unit' => $product->sale_unit_id !== null
                    ? $this->refs->saleUnitName((int) $product->sale_unit_id)
                    : null,
                'image' => $primary !== null ? MediaUrl::of((int) $primary->media_id) : null,
            ];
        });
    }

    public function findByBarcode(string $barcode): ?array
    {
        return Tenant::withoutScope(function () use ($barcode): ?array {
            $variant = ProductVariant::query()->where('barcode', $barcode)->first();
            if ($variant !== null) {
                return ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id];
            }

            $product = Product::query()->where('barcode', $barcode)->first();
            if ($product === null) {
                return null;
            }

            return ['product_id' => (int) $product->id, 'variant_id' => null];
        });
    }

    public function variantPriceOverride(?int $variantId): ?int
    {
        if ($variantId === null || $variantId <= 0) {
            return null;
        }

        $value = Tenant::withoutScope(
            fn () => ProductVariant::query()->whereKey($variantId)->value('price_override')
        );

        return $value !== null ? (int) $value : null;
    }

    public function countCategoriesUnderRoot(int $rootCategoryId): int
    {
        // acrossChannels(), per rule 10: the platform back office asks how many channel
        // categories hang off a root category before disabling it (EP-AD-043C). The
        // question spans every channel by definition, and the answer is a number.
        return Category::query()
            ->acrossChannels()
            ->where('root_category_id', $rootCategoryId)
            ->count();
    }

    public function countActiveProductsUnderRoot(int $rootCategoryId): int
    {
        // acrossChannels(), per rule 10: the channel categories under the root, from
        // every channel, for the same platform impact count (EP-AD-043C).
        $categoryIds = Category::query()
            ->acrossChannels()
            ->where('root_category_id', $rootCategoryId)
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return 0;
        }

        // acrossChannels(), per rule 10: the active products in those categories, again
        // across every channel. A number for a refusal message, never a row.
        return Product::query()
            ->acrossChannels()
            ->where('status', ProductStatus::Active)
            ->whereIn('category_id', $categoryIds)
            ->count();
    }

    public function countProductsUsingSaleUnit(int $saleUnitId): int
    {
        // acrossChannels(), per rule 10: platform impact count before a sale unit is
        // disabled (EP-AD-043D). Every channel, a number only.
        return Product::query()
            ->acrossChannels()
            ->where('sale_unit_id', $saleUnitId)
            ->count();
    }

    public function countInChannel(int $channelId): int
    {
        return Tenant::as($channelId, fn (): int => Product::query()->count());
    }
}
