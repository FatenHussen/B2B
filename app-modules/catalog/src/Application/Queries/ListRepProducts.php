<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Application\Support\RepCategoryIndex;
use Modules\Catalog\Application\Support\RepVariantSelection;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Enums\ProductMediaRole;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductUnitFactor;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\OfferFeed;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\StockedProductIds;
use Modules\Core\Support\MediaUrl;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListRepProducts
{
    public function __construct(
        private readonly RepSellingContext $selling,
        private readonly PricingEngine $pricing,
        private readonly AvailabilityClassifier $availability,
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
        private readonly RepVariantSelection $selection,
        private readonly OfferFeed $offers,
        private readonly RepCategoryIndex $categories,
        private readonly StockedProductIds $stocked,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function __invoke(object $user, Request $request): LengthAwarePaginator
    {
        $ctx = $this->selling->for($user);
        $channelIds = $ctx['channel_ids'];
        if ($request->filled('filter.channel_id')) {
            $filter = (int) $request->input('filter.channel_id');
            $channelIds = in_array($filter, $channelIds, true) ? [$filter] : [0];
        }

        // Lifted, per rule 10: the rep app sets no tenant; `whereIn('supply_channel_id', …)`
        // below is the rep's own channel, and the isolation.
        $base = Product::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds === [] ? [0] : $channelIds)
            ->where('status', ProductStatus::Active)
            ->where(function ($q): void {
                // Same hide rule as VisibleCatalogQuery: disabled brand → products drop from apps.
                $q->whereNull('brand_id')
                    ->orWhereHas('brand', fn ($b) => $b->where('status', BrandStatus::Active));
            })
            ->with(['brand', 'variants', 'media']);

        if ($request->filled('barcode')) {
            $base->where('barcode', $request->string('barcode')->toString());
        }

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('category_id', function ($query, $value) use ($channelIds): void {
                    $id = (int) $value;
                    if ($id <= 0) {
                        return;
                    }
                    $branch = $this->categories->branch($channelIds, $id);
                    $query->whereIn('category_id', $branch === [] ? [0] : $branch);
                }),
                AllowedFilter::callback('root_category_id', function ($query, $value): void {
                    $id = (int) $value;
                    if ($id <= 0) {
                        return;
                    }
                    $query->whereHas('category', fn ($category) => $category->where('root_category_id', $id));
                }),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::exact('channel_id', 'supply_channel_id'),
                AllowedFilter::callback('available_only', function ($query, $value) use ($channelIds): void {
                    if (! in_array((string) $value, ['1', 'true'], true)) {
                        return;
                    }
                    $stocked = $this->stocked->ids($channelIds);
                    $query->where(function ($q) use ($stocked): void {
                        $q->where('tracked', false);
                        if ($stocked !== []) {
                            $q->orWhereIn('id', $stocked);
                        }
                    });
                }),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->where('name_ar', 'like', '%'.$value.'%')->orWhere('sku', 'like', '%'.$value.'%');
                    });
                }),
            )
            ->defaultSort('-created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));
    }

    /**
     * @return array<string, mixed>
     */
    public function map(object $user, Product $product): array
    {
        $quoted = $this->pricing->quoteLine(
            (int) $product->id,
            1,
            $this->zoneId($user),
            null,
            (int) $product->supply_channel_id,
        );

        $product->loadMissing(['brand', 'variants', 'media']);
        $image = $product->media
            ->first(fn ($m) => $m->role !== ProductMediaRole::Video);
        $ctx = $this->selling->for($user);
        $zoneId = $this->zoneId($user);
        $activityId = (int) ($ctx['activity_type_id'] ?? 0);

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name_ar,
            'image' => $image !== null ? MediaUrl::of((int) $image->media_id) : null,
            'brand' => $product->brand === null ? null : [
                'id' => (int) $product->brand->id,
                'name' => (string) $product->brand->name_ar,
            ],
            'channel' => [
                'id' => (int) $product->supply_channel_id,
                'name' => $this->channels->name((int) $product->supply_channel_id),
            ],
            'price' => [
                'type' => $quoted['type'],
                'value' => $quoted['unit_price'],
                'label' => $quoted['label'],
            ],
            'availability' => $this->availability->classify((int) $product->id),
            'sale_unit' => $product->sale_unit_id !== null
                ? $this->refs->saleUnitName((int) $product->sale_unit_id)
                : null,
            'created_at' => $product->created_at?->timezone('Asia/Damascus')->toIso8601String(),
            'has_offer' => $zoneId > 0 && $activityId > 0 && $this->offers->productHasOffer((int) $product->id, $zoneId, $activityId),
            'variants' => $this->selection->orderable($product->variants)->map(fn (ProductVariant $variant): array => [
                'id' => (int) $variant->id,
                'label' => $this->selection->label($variant),
                'barcode' => $variant->barcode,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(object $user, Product $product): array
    {
        $product->loadMissing(['brand', 'variants', 'media', 'category', 'variantAxes.values', 'specs', 'unitFactors']);

        $card = $this->map($user, $product);
        $orderable = $this->selection->orderable($product->variants);
        $zoneId = $this->zoneId($user);

        $card['variants'] = $orderable->map(function (ProductVariant $variant) use ($product, $zoneId): array {
            $quoted = $this->pricing->quoteLine(
                (int) $product->id,
                1,
                $zoneId,
                null,
                (int) $product->supply_channel_id,
                [],
                (int) $variant->id,
            );

            return [
                'id' => (int) $variant->id,
                'label' => $this->selection->label($variant),
                'barcode' => $variant->barcode,
                'sku' => (string) $variant->sku,
                'image' => MediaUrl::of($variant->image_media_id !== null ? (int) $variant->image_media_id : null),
                'price' => $quoted['unit_price'],
                'attributes' => $this->selection->attributes($variant),
            ];
        })->all();
        $card['attributes_map'] = $this->selection->map($product, $orderable);
        $card['images'] = $product->media
            ->filter(fn ($m) => $m->role !== ProductMediaRole::Video)
            ->map(fn ($m) => MediaUrl::of((int) $m->media_id))
            ->filter()
            ->values()
            ->all();
        $card['short_description'] = $product->short_description;
        $card['long_description'] = $product->long_description;
        $card['category'] = $product->category === null ? null : [
            'id' => (int) $product->category->id,
            'name' => (string) $product->category->name,
        ];
        $card['specs'] = $product->specs
            ->map(fn ($spec): array => ['key' => (string) $spec->key, 'value' => (string) $spec->value])
            ->all();
        $card['sale_unit'] = $product->sale_unit_id !== null ? $this->namedUnit((int) $product->sale_unit_id) : null;
        $card['min_order_qty'] = (int) $product->min_order_qty;
        $card['order_multiple'] = (int) $product->order_multiple;
        $card['unit_factors'] = $product->unitFactors
            ->map(fn (ProductUnitFactor $factor): array => [
                'from_unit' => $this->namedUnit((int) $factor->from_unit_id),
                'to_unit' => $this->namedUnit((int) $factor->to_unit_id),
                'factor' => (int) $factor->factor,
            ])
            ->all();

        return $card;
    }

    private function zoneId(object $user): int
    {
        $ctx = $this->selling->for($user);

        return (int) (request('zone') ?: request('filter.zone_id') ?: $ctx['default_zone_id'] ?: 0);
    }

    /**
     * @return array{id: int, name: string|null}
     */
    private function namedUnit(int $id): array
    {
        return [
            'id' => $id,
            'name' => $this->refs->saleUnitName($id),
        ];
    }
}
