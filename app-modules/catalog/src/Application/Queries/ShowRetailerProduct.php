<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Http\Request;
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
            ->with(['brand', 'media', 'specs', 'variants'])
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

        return $card + [
            'images' => $images,
            'model_no' => $product->model_no,
            'long_description' => $product->long_description,
            'specs' => $product->specs->map(fn ($s) => ['key' => $s->key, 'value' => $s->value])->all(),
            'variants' => $variants,
            'sliders' => [
                'price_comparison' => [],
                'same_brand' => [],
                'similar' => [],
                'suggested' => [],
            ],
        ];
    }
}
