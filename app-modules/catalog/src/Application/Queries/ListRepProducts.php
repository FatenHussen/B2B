<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Enums\ProductMediaRole;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\RepSellingContext;
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
    ) {}

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
            ->with(['brand', 'variants', 'media']);

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::exact('channel_id', 'supply_channel_id'),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->where('name_ar', 'like', '%'.$value.'%')->orWhere('sku', 'like', '%'.$value.'%');
                    });
                }),
            )
            ->when($request->filled('barcode'), fn ($q) => $q->where('barcode', $request->string('barcode')->toString()))
            ->defaultSort('-created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));
    }

    /**
     * @return array<string, mixed>
     */
    public function map(object $user, Product $product): array
    {
        $ctx = $this->selling->for($user);
        $zoneId = (int) (request('zone') ?: request('filter.zone_id') ?: $ctx['default_zone_id'] ?: 0);
        $quoted = $this->pricing->quoteLine(
            (int) $product->id,
            1,
            $zoneId,
            null,
            (int) $product->supply_channel_id,
        );

        $product->loadMissing(['brand', 'variants', 'media']);
        $image = $product->media
            ->first(fn ($m) => $m->role !== ProductMediaRole::Video);

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
            'variants' => $product->variants->map(fn (ProductVariant $v): array => [
                'id' => (int) $v->id,
                'label' => $this->variantLabel($v),
                'barcode' => $v->barcode,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(object $user, Product $product): array
    {
        $card = $this->map($user, $product);
        $card['images'] = $product->media
            ->filter(fn ($m) => $m->role !== ProductMediaRole::Video)
            ->map(fn ($m) => MediaUrl::of((int) $m->media_id))
            ->filter()
            ->values()
            ->all();
        $card['long_description'] = $product->long_description;

        return $card;
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $combination = $variant->combination;
        if (! is_array($combination) || $combination === []) {
            return (string) ($variant->sku !== null && $variant->sku !== '' ? $variant->sku : 'متغير');
        }

        $parts = [];
        foreach ($combination as $key => $value) {
            $parts[] = is_string($key) && ! is_numeric($key)
                ? $key.': '.(string) $value
                : (string) $value;
        }

        return implode(' / ', $parts);
    }
}
