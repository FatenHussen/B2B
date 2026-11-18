<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Modules\Catalog\Application\Support\ProductCardAssembler;
use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Catalog\Domain\Enums\ProductMediaRole;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\RetailerProductFavorite;
use Modules\Core\Contracts\AvailabilityClassifier;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Support\MediaUrl;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowRetailerProduct
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly ProductCardAssembler $cards,
        private readonly PricingEngine $pricing,
        private readonly AvailabilityClassifier $availability,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $id): array
    {
        $ctx = $this->shopping->for($user);
        $product = VisibleCatalogQuery::products($ctx)
            ->with(['brand', 'media', 'specs', 'variants', 'variantAxes.values', 'unitFactors'])
            ->whereKey($id)
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException;
        }

        $favorite = RetailerProductFavorite::query()
            ->where('retailer_id', $ctx['retailer_id'])
            ->where('product_id', $product->id)
            ->exists();

        $card = $this->cards->card($product, $ctx, $favorite);
        $images = $product->media
            ->filter(fn ($m) => $m->role !== ProductMediaRole::Video)
            ->map(fn ($m) => MediaUrl::of((int) $m->media_id))
            ->filter()
            ->values()
            ->all();

        $specs = $product->specs->map(fn ($s) => ['key' => $s->key, 'value' => $s->value])->all();
        $specMap = collect($specs)->mapWithKeys(fn ($s) => [(string) $s['key'] => (string) $s['value']]);

        $variants = $product->variants->map(function ($v) use ($product, $ctx) {
            $quoted = $this->pricing->quoteLine(
                (int) $product->id,
                1,
                (int) $ctx['zone_id'],
                (int) $ctx['retailer_id'],
                (int) $product->supply_channel_id,
            );

            return [
                'id' => (int) $v->id,
                'combination' => $v->combination,
                'price' => $quoted['unit_price'],
                'availability' => $this->availability->classify((int) $product->id),
                'barcode' => $v->barcode,
            ];
        })->all();

        $variantAxes = $product->variantAxes->map(fn ($axis) => [
            'key' => (string) $axis->name,
            'values' => $axis->values->pluck('value')->map(fn ($v) => (string) $v)->values()->all(),
        ])->all();

        $sameBrand = [];
        if ($product->brand_id) {
            $sameBrand = VisibleCatalogQuery::products($ctx)
                ->with(['brand', 'media', 'unitFactors', 'variants'])
                ->where('brand_id', $product->brand_id)
                ->whereKeyNot($product->id)
                ->limit(10)
                ->get()
                ->map(function (Product $p) use ($ctx) {
                    $fav = RetailerProductFavorite::query()
                        ->where('retailer_id', $ctx['retailer_id'])
                        ->where('product_id', $p->id)
                        ->exists();

                    return $this->cards->card($p, $ctx, $fav);
                })
                ->all();
        }

        $similar = [];
        if ($product->category_id) {
            $similar = VisibleCatalogQuery::products($ctx)
                ->with(['brand', 'media', 'unitFactors', 'variants'])
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->limit(10)
                ->get()
                ->map(function (Product $p) use ($ctx) {
                    $fav = RetailerProductFavorite::query()
                        ->where('retailer_id', $ctx['retailer_id'])
                        ->where('product_id', $p->id)
                        ->exists();

                    return $this->cards->card($p, $ctx, $fav);
                })
                ->all();
        }

        $storefront = is_array($product->storefront) ? $product->storefront : [];
        $featureLines = is_array($storefront['features'] ?? null) ? $storefront['features'] : [];
        $features = $featureLines !== []
            ? array_map(fn ($text) => ['text' => (string) $text], $featureLines)
            : $product->specs
                ->filter(fn ($s) => str_starts_with((string) $s->key, 'feature.') || (string) $s->key === 'ميزة')
                ->map(fn ($s) => ['text' => (string) $s->value])
                ->values()
                ->all();
        if ($features === []) {
            $features = [
                ['text' => 'مناسب للبيع بالتجزئة والجملة'],
                ['text' => 'تعبئة تحافظ على الجودة'],
            ];
        }

        $gross = $storefront['gross_weight_gram'] ?? $specMap->get('gross_weight_gram') ?? $specMap->get('الوزن الإجمالي');
        $cartonWeight = $storefront['carton_weight_gram'] ?? $specMap->get('carton_weight_gram') ?? $specMap->get('وزن الكرتون');

        return $card + [
            'images' => $images,
            'model_no' => $product->model_no,
            'short_description' => $product->short_description,
            'long_description' => $product->long_description,
            'features' => $features,
            'specs' => $specs,
            'dimensions' => [
                'net_weight_gram' => $product->weight_gram !== null ? (int) $product->weight_gram : null,
                'gross_weight_gram' => is_numeric($gross) ? (int) $gross : null,
                'height_mm' => $product->height_mm !== null ? (int) $product->height_mm : null,
                'width_mm' => $product->width_mm !== null ? (int) $product->width_mm : null,
                'length_mm' => $product->length_mm !== null ? (int) $product->length_mm : null,
            ],
            'packaging' => [
                'container_type' => $storefront['container_type'] ?? $specMap->get('container_type') ?? $specMap->get('نوع العبوة') ?? 'بلاستيك',
                'capacity' => $storefront['capacity'] ?? $specMap->get('capacity') ?? $specMap->get('السعة') ?? ($card['sale_unit_label'] ?? '1 وحدة'),
                'pieces_per_carton' => $card['pieces_per_sale_unit'] ?? 6,
                'carton_dimensions' => $storefront['carton_dimensions'] ?? $specMap->get('carton_dimensions') ?? $specMap->get('أبعاد الكرتون') ?? '40 × 32 × 27 سم',
                'carton_weight_gram' => is_numeric($cartonWeight) ? (int) $cartonWeight : 12000,
            ],
            'variants' => $variants,
            'variant_axes' => $variantAxes,
            'sliders' => [
                'price_comparison' => [],
                'same_brand' => $sameBrand,
                'similar' => $similar,
                'suggested' => $similar,
            ],
        ];
    }
}
