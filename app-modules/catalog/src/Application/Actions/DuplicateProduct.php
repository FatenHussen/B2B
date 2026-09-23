<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Enums\ProductStatus;
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
use Modules\Core\Contracts\ProductPricingReader;
use Modules\Core\Contracts\ProductPricingWriter;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class DuplicateProduct
{
    public function __construct(
        private readonly ProductPricingReader $pricingReader,
        private readonly ProductPricingWriter $pricingWriter,
        private readonly ChannelLimits $limits,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{sku: string, name_ar?: string|null}  $data
     * @return array{id: int, sku: string}
     */
    public function __invoke(object $actor, int $productId, array $data): array
    {
        $source = Product::query()->whereKey($productId)->first();
        if ($source === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $sku = (string) ($data['sku'] ?? '');
        if ($sku === '') {
            InvalidFields::throw(['sku' => 'catalog.duplicate_sku_required']);
        }

        if (Product::query()->where('sku', $sku)->exists()) {
            InvalidFields::throw(['sku' => 'catalog.sku_taken']);
        }

        $channelId = (int) Tenant::currentId();
        $this->limits->assertCanAdd($channelId, 'skus', Product::query()->count());

        $clone = DB::transaction(function () use ($source, $data, $sku, $channelId): Product {
            $clone = Product::query()->create([
                'name_ar' => (string) ($data['name_ar'] ?? $source->name_ar),
                'name_en' => $source->name_en,
                'sku' => $sku,
                'brand_id' => $source->brand_id,
                'category_id' => $source->category_id,
                'model_no' => $source->model_no,
                'barcode' => null,
                'status' => ProductStatus::Draft,
                'short_description' => $source->short_description,
                'long_description' => $source->long_description,
                'sale_unit_id' => $source->sale_unit_id,
                'min_order_qty' => $source->min_order_qty,
                'order_multiple' => $source->order_multiple,
                'weight_gram' => $source->weight_gram,
                'tracked' => $source->tracked,
                'reorder_point' => $source->reorder_point,
                'allow_backorder' => $source->allow_backorder,
                'lead_time_days' => $source->lead_time_days,
                'priority' => $source->priority,
                'tags' => $source->tags,
            ]);

            foreach (ProductSpec::query()->where('product_id', $source->id)->orderBy('order')->get() as $spec) {
                ProductSpec::query()->create([
                    'product_id' => $clone->id,
                    'key' => $spec->key,
                    'value' => $spec->value,
                    'order' => $spec->order,
                ]);
            }

            foreach (ProductMedia::query()->where('product_id', $source->id)->orderBy('order')->get() as $media) {
                ProductMedia::query()->create([
                    'product_id' => $clone->id,
                    'media_id' => $media->media_id,
                    'role' => $media->role,
                    'order' => $media->order,
                ]);
            }

            foreach (ProductUnitFactor::query()->where('product_id', $source->id)->get() as $factor) {
                ProductUnitFactor::query()->create([
                    'product_id' => $clone->id,
                    'from_unit_id' => $factor->from_unit_id,
                    'to_unit_id' => $factor->to_unit_id,
                    'factor' => $factor->factor,
                ]);
            }

            foreach (ProductZone::query()->where('product_id', $source->id)->get() as $zone) {
                ProductZone::query()->create(['product_id' => $clone->id, 'zone_id' => $zone->zone_id]);
            }

            foreach (ProductActivityType::query()->where('product_id', $source->id)->get() as $row) {
                ProductActivityType::query()->create([
                    'product_id' => $clone->id,
                    'activity_type_id' => $row->activity_type_id,
                ]);
            }

            foreach (ProductRetailerGroup::query()->where('product_id', $source->id)->get() as $row) {
                ProductRetailerGroup::query()->create([
                    'product_id' => $clone->id,
                    'group_id' => $row->group_id,
                ]);
            }

            foreach (ProductSliderTag::query()->where('product_id', $source->id)->get() as $row) {
                ProductSliderTag::query()->create([
                    'product_id' => $clone->id,
                    'slider_key' => $row->slider_key,
                ]);
            }

            $pricing = $this->pricingReader->show((int) $source->id);
            if ($pricing !== null) {
                $this->pricingWriter->replace($channelId, (int) $clone->id, PricingDraft::fromArray($pricing));
            }

            return $clone;
        });

        $this->audit->record('catalog.product.duplicate', $actor, 'product', (int) $clone->id, [
            'after' => ['source_id' => $productId, 'sku' => $sku],
        ], $channelId);

        return ['id' => (int) $clone->id, 'sku' => (string) $clone->sku];
    }
}
