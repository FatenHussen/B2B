<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListChannelRetailers
{
    public function __construct(private readonly ChannelDirectory $channels) {}

    /**
     * @return LengthAwarePaginator<int, RetailerProfile>
     */
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $channelId = (int) Tenant::currentId();
        $zoneIds = $this->channels->zoneIds($channelId);
        $perPage = min((int) $request->input('per_page', 25), 100);

        if ($zoneIds === []) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1);
        }

        return QueryBuilder::for(RetailerProfile::query()->whereIn('zone_id', $zoneIds))
            ->allowedFilters(
                AllowedFilter::exact('zone_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->where('shop_name', 'like', '%'.$value.'%')
                            ->orWhere('address', 'like', '%'.$value.'%');
                    });
                }),
            )
            ->defaultSort('shop_name')
            ->paginate($perPage);
    }

    /**
     * @return array{id: int, shop_name: string, phone: string|null, zone_id: int|null, status: string}
     */
    public function map(RetailerProfile $row): array
    {
        $phone = null;
        $ownerId = $row->getAttribute('app_user_id');
        if ($ownerId !== null) {
            $raw = AppUser::query()->whereKey((int) $ownerId)->value('phone');
            $phone = is_string($raw) && $raw !== '' ? $raw : null;
        }

        $status = $row->getAttribute('status');

        return [
            'id' => (int) $row->getKey(),
            'shop_name' => (string) $row->getAttribute('shop_name'),
            'phone' => $phone,
            'zone_id' => $row->getAttribute('zone_id') !== null ? (int) $row->getAttribute('zone_id') : null,
            'status' => is_object($status) && property_exists($status, 'value')
                ? (string) $status->value
                : (string) $status,
        ];
    }
}
