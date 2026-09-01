<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

use Modules\Catalog\Domain\Enums\ProductMediaRole;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\OfferFeed;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\MediaUrl;

final class ProductCardAssembler
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly AvailabilityClassifier $availability,
        private readonly OfferFeed $offers,
        private readonly ReferenceDirectory $refs,
    ) {}

    /**
     * @param  array{zone_id: int, activity_type_id: int, retailer_id?: int}  $ctx
     * @return array<string, mixed>
     */
    public function card(Product $product, array $ctx, bool $favorite): array
    {
        $quoted = $this->pricing->quoteLine(
            (int) $product->id,
            1,
            (int) $ctx['zone_id'],
            isset($ctx['retailer_id']) ? (int) $ctx['retailer_id'] : null,
            (int) $product->supply_channel_id,
        );

        $primary = $product->media
            ->first(fn ($m) => $m->role === ProductMediaRole::Primary)
            ?? $product->media->first();

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name_ar,
            'brand' => $product->brand ? [
                'id' => (int) $product->brand->id,
                'name' => (string) $product->brand->name_ar,
            ] : null,
            'image' => MediaUrl::of($primary?->media_id),
            'sale_unit' => $product->sale_unit_id ? $this->refs->saleUnitName((int) $product->sale_unit_id) : null,
            'min_order_qty' => (int) $product->min_order_qty,
            'price' => [
                'type' => $quoted['type'],
                'value' => $quoted['unit_price'],
                'from' => $quoted['from'] !== null ? $quoted['unit_price'] : $quoted['unit_price'],
                'to' => $quoted['to'] !== null ? $quoted['unit_price'] : $quoted['unit_price'],
                'label' => $quoted['label'],
            ],
            'discount' => 0,
            'price_after' => $quoted['unit_price'],
            'sold_count' => 0,
            'availability' => $this->availability->classify((int) $product->id),
            'lead_time_days' => $product->lead_time_days,
            'rating' => 0,
            'is_favorite' => $favorite,
            'has_offer' => $this->offers->productHasOffer(
                (int) $product->id,
                (int) $ctx['zone_id'],
                (int) $ctx['activity_type_id'],
            ),
            'variants_count' => $product->variants()->count(),
        ];
    }
}
