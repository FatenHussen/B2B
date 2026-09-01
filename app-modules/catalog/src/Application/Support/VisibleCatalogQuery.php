<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;

final class VisibleCatalogQuery
{
    /**
     * @param  array{zone_id: int, activity_type_id: int, channel_ids: list<int>, category_ids?: list<int>}  $shopping
     */
    public static function products(array $shopping): Builder
    {
        $channelIds = $shopping['channel_ids'] ?? [];

        return Product::withoutGlobalScope('channel')
            ->where('status', ProductStatus::Active)
            ->when($channelIds !== [], fn (Builder $q) => $q->whereIn('supply_channel_id', $channelIds))
            ->when($channelIds === [], fn (Builder $q) => $q->whereRaw('0 = 1'))
            ->whereHas('zones', fn ($q) => $q->where('zone_id', $shopping['zone_id']))
            ->whereHas('activityTypes', fn ($q) => $q->where('activity_type_id', $shopping['activity_type_id']))
            ->when(
                ($shopping['category_ids'] ?? []) !== [],
                fn (Builder $q) => $q->whereHas(
                    'category',
                    fn ($c) => $c->withoutGlobalScope('channel')
                        ->whereIn('root_category_id', $shopping['category_ids']),
                ),
            );
    }
}
