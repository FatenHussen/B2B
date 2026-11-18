<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

use Modules\Catalog\Domain\Enums\ProductMediaRole;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\ChannelDirectory;
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
        private readonly ChannelDirectory $channels,
    ) {}

    /**
     * @param  array{zone_id: int, activity_type_id: int, retailer_id?: int}  $ctx
     * @return array<string, mixed>
     */
    public function card(Product $product, array $ctx, bool $favorite): array
    {
        $product->loadMissing(['brand', 'media', 'unitFactors']);

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

        $saleUnit = $product->sale_unit_id ? $this->refs->saleUnitName((int) $product->sale_unit_id) : null;
        $pieces = $product->unitFactors->max('factor');
        $pieces = is_numeric($pieces) ? (int) $pieces : null;
        $saleUnitLabel = $saleUnit;
        if ($saleUnit !== null && $pieces !== null && $pieces > 1) {
            $saleUnitLabel = $saleUnit.' ('.$pieces.' قطعة)';
        }

        $value = (int) $quoted['unit_price'];
        $channelId = (int) $product->supply_channel_id;
        $companyName = $this->channels->name($channelId);
        $leadDays = $product->lead_time_days !== null ? (int) $product->lead_time_days : null;
        $id = (int) $product->id;
        $storefront = is_array($product->storefront) ? $product->storefront : [];
        $compare = isset($storefront['compare_price']) ? (int) $storefront['compare_price'] : (int) round($value * 1.25);
        if ($compare < $value) {
            $compare = $value;
        }
        $discount = $compare - $value;
        $percent = $compare > 0 ? (int) round(($discount * 100) / $compare) : 0;

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name_ar,
            'brand' => $product->brand ? [
                'id' => (int) $product->brand->id,
                'name' => (string) $product->brand->name_ar,
            ] : null,
            'company' => $companyName !== null
                ? ['id' => $channelId, 'name' => $companyName]
                : null,
            'image' => MediaUrl::of($primary?->media_id),
            'sale_unit' => $saleUnit,
            'sale_unit_label' => $saleUnitLabel,
            'pieces_per_sale_unit' => $pieces,
            'min_order_qty' => (int) $product->min_order_qty,
            'price' => [
                'type' => $quoted['type'],
                'value' => $value,
                'from' => $quoted['from'] !== null ? $value : $value,
                'to' => $quoted['to'] !== null ? $value : $value,
                'label' => $quoted['label'],
            ],
            'price_before' => $compare,
            'discount' => $discount,
            'discount_percent' => $percent,
            'price_after' => $value,
            'sold_count' => isset($storefront['sold_count']) ? (int) $storefront['sold_count'] : StorefrontNumbers::soldCount($id),
            'rating_count' => isset($storefront['rating_count']) ? (int) $storefront['rating_count'] : StorefrontNumbers::ratingCount($id),
            'availability' => $this->availability->classify((int) $product->id),
            'lead_time_days' => $leadDays,
            'lead_time_label' => $this->leadTimeLabel($leadDays),
            'rating' => isset($storefront['rating']) ? round((float) $storefront['rating'], 1) : StorefrontNumbers::rating($id),
            'is_favorite' => $favorite,
            'has_offer' => $this->offers->productHasOffer(
                (int) $product->id,
                (int) $ctx['zone_id'],
                (int) $ctx['activity_type_id'],
            ),
            'variants_count' => $product->relationLoaded('variants')
                ? $product->variants->count()
                : $product->variants()->count(),
        ];
    }

    private function leadTimeLabel(?int $days): ?string
    {
        if ($days === null) {
            return null;
        }
        if ($days <= 1) {
            return '24 ساعة';
        }

        return $days.'-'.($days + 3).' أيام';
    }
}
