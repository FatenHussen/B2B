<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure;

use Modules\Catalog\Domain\Enums\ProductStatus;
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
}
