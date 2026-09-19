<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepSourcedShop;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListChannelRepSourcedShops
{
    /**
     * @return LengthAwarePaginator<int, RepSourcedShop>
     */
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 25), 100);
        $channelId = (int) Tenant::currentId();
        $profileIds = RepProfile::query()->where('channel_id', $channelId)->pluck('id');

        return QueryBuilder::for(
            RepSourcedShop::query()->whereIn('rep_id', $profileIds)
        )
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-id')
            ->paginate($perPage);
    }

    /**
     * @return array{
     *     id: int,
     *     rep_user_id: int|null,
     *     shop_name: string,
     *     phone: string,
     *     zone_id: int,
     *     status: string,
     *     retailer_id: int|null
     * }
     */
    public function map(RepSourcedShop $row): array
    {
        $profile = RepProfile::query()->whereKey($row->rep_id)->first();

        return [
            'id' => (int) $row->id,
            'rep_user_id' => $profile !== null ? (int) $profile->app_user_id : null,
            'shop_name' => (string) $row->shop_name,
            'phone' => (string) $row->phone,
            'zone_id' => (int) $row->zone_id,
            'status' => $row->status->value,
            'retailer_id' => $row->retailer_id !== null ? (int) $row->retailer_id : null,
        ];
    }
}
