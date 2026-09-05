<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Core\Contracts\OfferFeed;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Support\MediaUrl;

final class RetailerHome
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly ReferenceDirectory $refs,
        private readonly OfferFeed $offers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user): array
    {
        $ctx = $this->shopping->for($user);
        $channelIds = $ctx['channel_ids'];

        $rootIds = VisibleCatalogQuery::products($ctx)
            ->with('category')
            ->get()
            ->pluck('category.root_category_id')
            ->filter()
            ->unique()
            ->all();

        $categories = array_values(array_filter(
            $this->refs->activeRootCategories(),
            fn (array $row) => $ctx['category_ids'] === [] || in_array($row['id'], $ctx['category_ids'], true),
        ));

        $brands = Brand::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds === [] ? [0] : $channelIds)
            ->where('status', BrandStatus::Active)
            ->when($ctx['activity_type_id'], fn ($q) => $q->whereHas(
                'activityTypes',
                fn ($a) => $a->where('activity_type_id', $ctx['activity_type_id']),
            ))
            ->orderBy('order')
            ->limit(20)
            ->get()
            ->map(fn (Brand $b) => [
                'id' => (int) $b->id,
                'name' => (string) $b->name_ar,
                'logo' => MediaUrl::of($b->logo_media_id ? (int) $b->logo_media_id : null),
            ])
            ->all();

        return [
            'header' => [
                'shop_name' => $ctx['shop_name'],
                'zone' => ['id' => $ctx['zone_id'], 'name' => $ctx['zone_name']],
                'points' => 0,
                'tier' => null,
                'unread_notifications' => 0,
                'pending_sync' => 0,
            ],
            'banner' => null,
            'quick_actions' => [
                ['key' => 'orders', 'count' => 0],
                ['key' => 'cart', 'count' => 0],
                ['key' => 'debts', 'count' => 0],
            ],
            'stats' => [
                'orders_count' => 0,
                'total_debt' => 0,
                'delivering_today' => 0,
            ],
            'categories' => array_map(fn (array $c) => [
                'id' => $c['id'],
                'name' => $c['name'],
                'image' => $c['image'],
            ], $categories),
            'offers_slider' => $this->offers->sliderFor($ctx['zone_id'], $ctx['activity_type_id'], $channelIds),
            'brands_slider' => $brands,
            'dynamic_sliders' => [],
        ];
    }
}
