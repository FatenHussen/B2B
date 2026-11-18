<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Application\Support\ProductCardAssembler;
use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\RetailerProductFavorite;
use Modules\Core\Contracts\OfferFeed;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Support\MediaUrl;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RetailerBrands
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly ReferenceDirectory $refs,
        private readonly ProductCardAssembler $cards,
        private readonly OfferFeed $offers,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginate(object $user, Request $request): LengthAwarePaginator
    {
        $ctx = $this->shopping->for($user);
        $channelIds = $ctx['channel_ids'] === [] ? [0] : $ctx['channel_ids'];

        // Lifted, per rule 10: no tenant on /app/retailer/*; the retailer's channels are the
        // filter on the next line.
        $base = Brand::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds)
            ->where('status', BrandStatus::Active);

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('activity_type_id', function ($query, $value): void {
                    $query->whereHas('activityTypes', fn ($q) => $q->where('activity_type_id', $value));
                }),
                AllowedFilter::callback('root_category_id', function ($query, $value) use ($ctx): void {
                    $ids = VisibleCatalogQuery::products($ctx)
                        ->whereHas('category', fn ($c) => $c->where('root_category_id', (int) $value))
                        ->pluck('brand_id')
                        ->filter()
                        ->unique()
                        ->all();
                    $query->whereIn('id', $ids === [] ? [0] : $ids);
                }),
            )
            ->defaultSort('order')
            ->paginate(min((int) $request->get('per_page', 25), 100));
    }

    /**
     * @param  array{zone_id: int, activity_type_id: int, channel_ids: list<int>, category_ids?: list<int>, retailer_id: int}  $ctx
     * @return array<string, mixed>
     */
    public function mapList(Brand $brand, array $ctx): array
    {
        $categories = VisibleCatalogQuery::products($ctx)
            ->where('brand_id', $brand->id)
            ->with('category')
            ->get()
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->values()
            ->all();

        return [
            'id' => (int) $brand->id,
            'name' => (string) $brand->name_ar,
            'logo' => MediaUrl::of($brand->logo_media_id ? (int) $brand->logo_media_id : null),
            'banner' => MediaUrl::of($brand->banner_media_id ? (int) $brand->banner_media_id : null),
            'description' => $brand->description,
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(object $user, int $id): array
    {
        $ctx = $this->shopping->for($user);
        $brand = Brand::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $ctx['channel_ids'] === [] ? [0] : $ctx['channel_ids'])
            ->where('status', BrandStatus::Active)
            ->with('activityTypes')
            ->find($id);

        if ($brand === null) {
            throw new NotFoundHttpException;
        }

        $activities = [];
        foreach ($brand->activityTypes as $row) {
            $activities[] = [
                'id' => (int) $row->activity_type_id,
                'name' => $this->refs->activityTypeName((int) $row->activity_type_id) ?? '',
            ];
        }

        $categories = VisibleCatalogQuery::products($ctx)
            ->where('brand_id', $brand->id)
            ->with('category')
            ->get()
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->values()
            ->all();

        $products = VisibleCatalogQuery::products($ctx)
            ->with(['brand', 'media', 'unitFactors', 'variants'])
            ->where('brand_id', $brand->id)
            ->limit(24)
            ->get()
            ->map(function (Product $p) use ($ctx) {
                $fav = RetailerProductFavorite::query()
                    ->where('retailer_id', $ctx['retailer_id'])
                    ->where('product_id', $p->id)
                    ->exists();

                return $this->cards->card($p, $ctx, $fav);
            })
            ->all();

        $offers = $this->offers->sliderFor(
            $ctx['zone_id'],
            $ctx['activity_type_id'],
            [(int) $brand->supply_channel_id],
        );

        return [
            'id' => (int) $brand->id,
            'banner' => MediaUrl::of($brand->banner_media_id ? (int) $brand->banner_media_id : null),
            'logo' => MediaUrl::of($brand->logo_media_id ? (int) $brand->logo_media_id : null),
            'name' => (string) $brand->name_ar,
            'activities' => $activities,
            'description' => $brand->description,
            'categories' => $categories,
            'offers' => $offers,
            'sliders' => [
                ['key' => 'best_selling', 'items' => $products],
            ],
        ];
    }
}
