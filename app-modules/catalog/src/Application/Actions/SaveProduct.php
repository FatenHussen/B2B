<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Application\Support\CatalogBarcode;
use Modules\Catalog\Domain\Enums\ProductMediaRole;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductActivityType;
use Modules\Catalog\Domain\Models\ProductMedia;
use Modules\Catalog\Domain\Models\ProductRetailerGroup;
use Modules\Catalog\Domain\Models\ProductSliderTag;
use Modules\Catalog\Domain\Models\ProductSpec;
use Modules\Catalog\Domain\Models\ProductUnitFactor;
use Modules\Catalog\Domain\Models\ProductZone;
use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Contracts\PricingDraft;
use Modules\Core\Contracts\ProductPricingWriter;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class SaveProduct
{
    public function __construct(
        private readonly ProductPricingWriter $pricing,
        private readonly ReferenceDirectory $refs,
        private readonly ChannelLimits $limits,
        private readonly RecordsAudit $audit,
        private readonly StockLedger $stock,
        private readonly WarehouseDirectory $warehouses,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int, sku?: string}
     */
    public function __invoke(object $actor, array $data, ?int $productId = null): array
    {
        $channelId = (int) Tenant::currentId();
        $sku = (string) $data['sku'];

        $dup = Product::query()
            ->where('sku', $sku)
            ->when($productId, fn ($q) => $q->where('id', '!=', $productId))
            ->exists();
        if ($dup) {
            InvalidFields::throw(['sku' => 'catalog.sku_taken']);
        }

        $barcode = isset($data['barcode']) ? trim((string) $data['barcode']) : '';
        if ($barcode !== '') {
            CatalogBarcode::assertUnique($barcode, $productId, null);
        }

        if ($productId === null) {
            // BE-T12: 423 `plan_limit_exceeded` naming the limit, from the one resolver
            // that knows the plan, the override and its expiry. Was a bespoke 422 here.
            $this->limits->assertCanAdd($channelId, 'skus', Product::query()->count());
        }

        $this->assertRefs($data);

        $product = DB::transaction(function () use ($actor, $data, $productId, $channelId, $sku): Product {
            $attrs = $this->attributes($data, $sku);
            $product = $productId
                ? tap(Product::query()->findOrFail($productId))->fill($attrs)->save()
                : Product::query()->create($attrs);

            $this->syncChildren($product, $data);

            if (isset($data['pricing']) && is_array($data['pricing'])) {
                $this->pricing->replace($channelId, (int) $product->id, PricingDraft::fromArray($data['pricing']));
            }

            if ($productId === null) {
                $this->seedOpeningStock($actor, $channelId, (int) $product->id, $data['inventory']['opening_stock'] ?? []);
            }

            $this->audit->record(
                $productId ? 'catalog.product.update' : 'catalog.product.create',
                $actor,
                'product',
                (int) $product->id,
                ['after' => ['sku' => $sku]],
                $channelId,
            );

            return $product->fresh() ?? $product;
        });

        return $productId
            ? ['id' => (int) $product->id]
            : ['id' => (int) $product->id, 'sku' => (string) $product->sku];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRefs(array $data): void
    {
        if (isset($data['brand_id']) && Brand::query()->whereKey((int) $data['brand_id'])->doesntExist()) {
            InvalidFields::throw(['brand_id' => 'catalog.brand_not_found']);
        }
        if (isset($data['category_id']) && Category::query()->whereKey((int) $data['category_id'])->doesntExist()) {
            InvalidFields::throw(['category_id' => 'catalog.category_not_found']);
        }
        if (isset($data['sale_unit_id']) && ! $this->refs->saleUnitExists((int) $data['sale_unit_id'])) {
            InvalidFields::throw(['sale_unit_id' => 'catalog.sale_unit_not_found']);
        }

        $zones = array_map('intval', $data['availability']['zone_ids'] ?? []);
        if (! $this->refs->allZonesExist($zones)) {
            InvalidFields::throw(['availability.zone_ids' => 'catalog.zone_not_found']);
        }
        $activities = array_map('intval', $data['availability']['activity_type_ids'] ?? []);
        if (! $this->refs->allActivityTypesExist($activities)) {
            InvalidFields::throw(['availability.activity_type_ids' => 'catalog.activity_type_not_found']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, string $sku): array
    {
        $inventory = $data['inventory'] ?? [];
        $availability = $data['availability'] ?? [];
        $marketing = $data['marketing'] ?? [];

        return [
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'sku' => $sku,
            'brand_id' => $data['brand_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'model_no' => $data['model_no'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'status' => ProductStatus::tryFrom((string) ($data['status'] ?? 'draft')) ?? ProductStatus::Draft,
            'short_description' => $data['short_description'] ?? null,
            'long_description' => $data['long_description'] ?? null,
            'sale_unit_id' => $data['sale_unit_id'] ?? null,
            'min_order_qty' => (int) ($data['min_order_qty'] ?? 1),
            'order_multiple' => (int) ($data['order_multiple'] ?? 1),
            'weight_gram' => $data['weight_gram'] ?? null,
            'length_mm' => $data['length_mm'] ?? null,
            'width_mm' => $data['width_mm'] ?? null,
            'height_mm' => $data['height_mm'] ?? null,
            'tracked' => (bool) ($inventory['tracked'] ?? false),
            'reorder_point' => (int) ($inventory['reorder_point'] ?? 0),
            'allow_backorder' => (bool) ($inventory['allow_backorder'] ?? false),
            'lead_time_days' => $availability['lead_time_days'] ?? null,
            'priority' => (int) ($marketing['priority'] ?? 0),
            'tags' => $marketing['tags'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncChildren(Product $product, array $data): void
    {
        $product->specs()->delete();
        foreach ($data['specs'] ?? [] as $i => $spec) {
            ProductSpec::query()->create([
                'product_id' => $product->id,
                'key' => $spec['key'],
                'value' => $spec['value'],
                'order' => $i,
            ]);
        }

        $product->media()->delete();
        $media = $data['media'] ?? [];
        $order = 0;
        if (! empty($media['primary'])) {
            ProductMedia::query()->create([
                'product_id' => $product->id,
                'media_id' => (int) $media['primary'],
                'role' => ProductMediaRole::Primary,
                'order' => $order++,
            ]);
        }
        foreach ($media['images'] ?? [] as $id) {
            if ((int) $id === (int) ($media['primary'] ?? 0)) {
                continue;
            }
            ProductMedia::query()->create([
                'product_id' => $product->id,
                'media_id' => (int) $id,
                'role' => ProductMediaRole::Image,
                'order' => $order++,
            ]);
        }
        if (! empty($media['video'])) {
            ProductMedia::query()->create([
                'product_id' => $product->id,
                'media_id' => (int) $media['video'],
                'role' => ProductMediaRole::Video,
                'order' => $order,
            ]);
        }

        $product->unitFactors()->delete();
        foreach ($data['unit_factors'] ?? [] as $factor) {
            ProductUnitFactor::query()->create([
                'product_id' => $product->id,
                'from_unit_id' => (int) $factor['from_unit_id'],
                'to_unit_id' => (int) $factor['to_unit_id'],
                'factor' => (int) $factor['factor'],
            ]);
        }

        $product->zones()->delete();
        foreach ($data['availability']['zone_ids'] ?? [] as $zoneId) {
            ProductZone::query()->create(['product_id' => $product->id, 'zone_id' => (int) $zoneId]);
        }

        $product->activityTypes()->delete();
        foreach ($data['availability']['activity_type_ids'] ?? [] as $typeId) {
            ProductActivityType::query()->create([
                'product_id' => $product->id,
                'activity_type_id' => (int) $typeId,
            ]);
        }

        ProductRetailerGroup::query()->where('product_id', $product->id)->delete();
        foreach ($data['availability']['retailer_group_ids'] ?? [] as $groupId) {
            ProductRetailerGroup::query()->create([
                'product_id' => $product->id,
                'group_id' => (int) $groupId,
            ]);
        }

        $product->sliderTags()->delete();
        foreach ($data['marketing']['sliders'] ?? [] as $key) {
            ProductSliderTag::query()->create([
                'product_id' => $product->id,
                'slider_key' => (string) $key,
            ]);
        }
    }

    /**
     * @param  list<array{warehouse_id?: int, qty?: int, variant_id?: int|null}>  $rows
     */
    private function seedOpeningStock(object $actor, int $channelId, int $productId, array $rows): void
    {
        foreach ($rows as $i => $row) {
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            if ($warehouseId < 1 || $qty < 1) {
                continue;
            }
            if (! $this->warehouses->belongsToChannel($warehouseId, $channelId)) {
                InvalidFields::throw(["inventory.opening_stock.{$i}.warehouse_id" => 'catalog.warehouse_not_found']);
            }
            $this->stock->adjust(
                $warehouseId,
                $productId,
                isset($row['variant_id']) ? (int) $row['variant_id'] : null,
                $qty,
                'opening_stock',
                $actor,
            );
        }
    }
}
