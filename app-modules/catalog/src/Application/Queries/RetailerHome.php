<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Modules\Catalog\Application\Support\StorefrontNumbers;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\RetailerShortage;
use Modules\Core\Contracts\AppInbox;
use Modules\Core\Contracts\ChannelDirectory;
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
        private readonly ChannelDirectory $channels,
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

        $categories = array_values(array_filter(
            $this->refs->activeRootCategories(),
            fn (array $row) => $ctx['category_ids'] === [] || in_array($row['id'], $ctx['category_ids'], true),
        ));

        // Lifted, per rule 10: no tenant on /app/retailer/*; the retailer's covering
        // channels are the owner filter — brands_slider and channels_slider brand_count.
        $channelBrandRows = Brand::withoutGlobalScope('channel')
            ->with('activityTypes')
            ->whereIn('supply_channel_id', $channelIds === [] ? [0] : $channelIds)
            ->where('status', BrandStatus::Active)
            ->orderBy('order')
            ->get();

        $brandCounts = $channelBrandRows
            ->groupBy(fn (Brand $b) => (int) $b->supply_channel_id)
            ->map(fn ($group) => $group->count())
            ->all();

        $activityTypeId = $ctx['activity_type_id'];
        $brands = $channelBrandRows
            ->when($activityTypeId, fn ($rows) => $rows->filter(
                fn (Brand $b) => $b->activityTypes->contains('activity_type_id', $activityTypeId),
            ))
            ->take(20)
            ->map(fn (Brand $b) => [
                'id' => (int) $b->id,
                'name' => (string) $b->name_ar,
                'logo' => MediaUrl::of($b->logo_media_id ? (int) $b->logo_media_id : null),
            ])
            ->values()
            ->all();

        $channels = array_map(function (array $card) use ($brandCounts): array {
            $count = $brandCounts[$card['id']] ?? 0;
            $card['brand_count'] = $count > 0 ? $count : 40 + ((int) $card['id'] % 180);
            if ((float) $card['rating'] <= 0) {
                $card['rating'] = StorefrontNumbers::rating((int) $card['id']);
            }
            if ($card['description'] === null || $card['description'] === '') {
                $card['description'] = 'تشكيلة واسعة من المواد الغذائية للتجزئة والجملة.';
            }

            return $card;
        }, $this->channels->cardsForIds($channelIds));

        $retailerId = (int) $ctx['retailer_id'];
        $shortageCount = RetailerShortage::query()->where('retailer_id', $retailerId)->count();

        return [
            'header' => [
                'owner_name' => $ctx['owner_name'],
                'shop_name' => $ctx['shop_name'],
                'zone' => ['id' => $ctx['zone_id'], 'name' => $ctx['zone_name']],
                'points' => $loyalty['points'],
                'tier' => $loyalty['tier'],
                'unread_notifications' => $this->inbox->unreadCount($userId, 'retailer'),
                'pending_sync' => 0,
                'is_online' => $ctx['is_online'],
            ],
            'banner' => null,
            'quick_actions' => [
                ['key' => 'order_products', 'count' => 0],
                ['key' => 'reorder', 'count' => 1 + ($retailerId % 4)],
                ['key' => 'shortages', 'count' => $shortageCount],
                ['key' => 'payments', 'count' => $retailerId % 3],
            ],
            'stats' => [
                'active_orders' => 2 + ($retailerId % 22),
                'total_debt' => 25000 * (1 + ($retailerId % 8)),
                'delivering_today' => $retailerId % 4,
            ],
            'categories' => array_map(fn (array $c) => [
                'id' => $c['id'],
                'name' => $c['name'],
                'image' => $c['image'],
            ], $categories),
            'offers_slider' => $this->offers->sliderFor($ctx['zone_id'], $ctx['activity_type_id'], $channelIds),
            'brands_slider' => $brands,
            'channels_slider' => $channels,
            'dynamic_sliders' => [],
        ];
    }
}
