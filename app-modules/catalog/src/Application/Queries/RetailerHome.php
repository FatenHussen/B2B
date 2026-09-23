<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Core\Contracts\AppInbox;
use Modules\Core\Contracts\LoyaltyBalance;
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
        private readonly LoyaltyBalance $loyalty,
        private readonly AppInbox $inbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user): array
    {
        $ctx = $this->shopping->for($user);
        $channelIds = $ctx['channel_ids'];
        $userId = (int) $user->getAuthIdentifier();
        $loyalty = $this->loyalty->snapshot($userId, 'retailer');

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

        // Lifted, per rule 10: no tenant on /app/retailer/*; the retailer's channels are the
        // filter on the next line.
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
                'points' => $loyalty['points'],
                'tier' => $loyalty['tier'],
                'unread_notifications' => $this->inbox->unreadCount($userId, 'retailer'),
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
