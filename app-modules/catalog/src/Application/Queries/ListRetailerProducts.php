<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Application\Support\ProductCardAssembler;
use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Catalog\Domain\Models\RetailerProductFavorite;
use Modules\Core\Contracts\OfferFeed;
use Modules\Core\Contracts\RetailerShoppingContext;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListRetailerProducts
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly ProductCardAssembler $cards,
        private readonly OfferFeed $offers,
    ) {}

    public function paginate(object $user, Request $request): LengthAwarePaginator
    {
        $ctx = $this->shopping->for($user);
        $base = VisibleCatalogQuery::products($ctx)->with(['brand', 'media', 'variants']);

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::callback('available_only', function ($query, $value): void {
                    if ((string) $value === '1') {
                        $query->where('status', 'active');
                    }
                }),
                AllowedFilter::callback('offer_only', function ($query, $value): void {
                    if ((string) $value !== '1') {
                        return;
                    }
                    $query->where(function ($q): void {
                        $q->whereRaw('0 = 1');
                    });
                }),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->where('name_ar', 'like', '%'.$value.'%')
                            ->orWhere('sku', 'like', '%'.$value.'%');
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
    public function cardFor(object $user, $product): array
    {
        $ctx = $this->shopping->for($user);
        $favorite = RetailerProductFavorite::query()
            ->where('retailer_id', $ctx['retailer_id'])
            ->where('product_id', $product->id)
            ->exists();

        return $this->cards->card($product, $ctx, $favorite);
    }
}
