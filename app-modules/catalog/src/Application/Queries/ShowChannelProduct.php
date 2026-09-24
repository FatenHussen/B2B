<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Modules\Catalog\Domain\Enums\ProductMediaRole;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductActivityType;
use Modules\Catalog\Domain\Models\ProductMedia;
use Modules\Catalog\Domain\Models\ProductRetailerGroup;
use Modules\Catalog\Domain\Models\ProductSliderTag;
use Modules\Catalog\Domain\Models\ProductSpec;
use Modules\Catalog\Domain\Models\ProductUnitFactor;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Catalog\Domain\Models\ProductVariantAxis;
use Modules\Catalog\Domain\Models\ProductZone;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\ProductPricingReader;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;

final class ShowChannelProduct
{
    public function __construct(
        private readonly ProductPricingReader $pricing,
        private readonly PricingEngine $engine,
        private readonly StockLedger $ledger,
        private readonly WarehouseDirectory $warehouses,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(int $id): array
    {
        $product = Product::query()->whereKey($id)->first();

        if ($product === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $media = ProductMedia::query()
            ->where('product_id', $product->id)
            ->orderBy('order')
            ->get();

        $images = $media
            ->filter(fn (ProductMedia $m) => $m->role === ProductMediaRole::Image || $m->role === ProductMediaRole::Primary)
            ->values();
        $primary = $media->first(fn (ProductMedia $m) => $m->role === ProductMediaRole::Primary)
            ?? $images->first();
        $video = $media->first(fn (ProductMedia $m) => $m->role === ProductMediaRole::Video);

        $quoted = $this->engine->quoteLine(
            (int) $product->id,
            1,
            0,
            null,
            (int) $product->supply_channel_id,
        );

        $axes = ProductVariantAxis::query()
            ->where('product_id', $product->id)
            ->orderBy('order')
            ->with('values')
            ->get()
            ->map(fn (ProductVariantAxis $axis) => [
                'name' => $axis->name,
                'values' => $axis->values->pluck('value')->map(fn ($v) => (string) $v)->values()->all(),
            ])
            ->all();

        $warehouseId = $this->warehouses->defaultIdForChannel((int) $product->supply_channel_id);

        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get()
            ->map(function (ProductVariant $v) use ($product, $warehouseId): array {
                $priceQuote = $this->engine->quoteLine(
                    (int) $product->id,
                    1,
                    0,
                    null,
                    (int) $product->supply_channel_id,
                    [],
                    (int) $v->id,
                );

                $stock = 0;
                if ($warehouseId !== null) {
                    $stock = $this->ledger->available(
                        $warehouseId,
                        (int) $product->id,
                        (int) $v->id,
                    );
                }

                return [
                    'id' => (int) $v->id,
                    'sku' => (string) $v->sku,
                    'combination' => $v->combination,
                    'barcode' => $v->barcode,
                    'image' => $v->image_media_id !== null ? (string) $v->image_media_id : null,
                    'status' => (string) $v->status,
                    'price_override' => $v->price_override !== null ? (int) $v->price_override : null,
                    'price' => $priceQuote['unit_price'],
                    'stock' => $stock,
                ];
            })
            ->all();

        return [
            'id' => (int) $product->id,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'sku' => $product->sku,
            'brand_id' => $product->brand_id !== null ? (int) $product->brand_id : null,
            'category_id' => $product->category_id !== null ? (int) $product->category_id : null,
            'model_no' => $product->model_no,
            'barcode' => $product->barcode,
            'status' => $product->status->value,
            'short_description' => $product->short_description,
            'long_description' => $product->long_description,
            'specs' => ProductSpec::query()
                ->where('product_id', $product->id)
                ->orderBy('order')
                ->get()
                ->map(fn (ProductSpec $s) => ['key' => $s->key, 'value' => $s->value])
                ->all(),
            'media' => [
                'images' => $images->map(fn (ProductMedia $m) => $m->media_id)->values()->all(),
                'primary' => $primary?->media_id,
                'video' => $video?->media_id,
            ],
            'sale_unit_id' => $product->sale_unit_id !== null ? (int) $product->sale_unit_id : null,
            'unit_factors' => ProductUnitFactor::query()
                ->where('product_id', $product->id)
                ->get()
                ->map(fn (ProductUnitFactor $u) => [
                    'from_unit_id' => (int) $u->from_unit_id,
                    'to_unit_id' => (int) $u->to_unit_id,
                    'factor' => (int) $u->factor,
                ])
                ->all(),
            'min_order_qty' => (int) $product->min_order_qty,
            'order_multiple' => (int) $product->order_multiple,
            'weight_gram' => $product->weight_gram !== null ? (int) $product->weight_gram : null,
            'length_mm' => $product->length_mm !== null ? (int) $product->length_mm : null,
            'width_mm' => $product->width_mm !== null ? (int) $product->width_mm : null,
            'height_mm' => $product->height_mm !== null ? (int) $product->height_mm : null,
            'pricing' => $this->pricing->show((int) $product->id),
            'inventory' => [
                'tracked' => (bool) $product->tracked,
                'reorder_point' => $product->reorder_point !== null ? (int) $product->reorder_point : null,
                'allow_backorder' => (bool) $product->allow_backorder,
            ],
            'availability' => [
                'zone_ids' => ProductZone::query()
                    ->where('product_id', $product->id)
                    ->pluck('zone_id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                'activity_type_ids' => ProductActivityType::query()
                    ->where('product_id', $product->id)
                    ->pluck('activity_type_id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                'retailer_group_ids' => ProductRetailerGroup::query()
                    ->where('product_id', $product->id)
                    ->pluck('group_id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                'lead_time_days' => $product->lead_time_days !== null ? (int) $product->lead_time_days : null,
            ],
            'marketing' => [
                'tags' => is_array($product->tags) ? $product->tags : [],
                'sliders' => ProductSliderTag::query()
                    ->where('product_id', $product->id)
                    ->pluck('slider_key')
                    ->map(fn ($key) => (string) $key)
                    ->all(),
                'priority' => (int) $product->priority,
            ],
            'axes' => $axes,
            'variants' => $variants,
        ];
    }
}
