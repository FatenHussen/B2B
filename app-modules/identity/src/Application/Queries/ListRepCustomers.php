<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepSourcedShop;
use Modules\Identity\Domain\Models\RetailerProfile;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListRepCustomers
{
    public function __construct(private readonly RepSellingContext $selling) {}

    public function __invoke(AppUser $user): LengthAwarePaginator
    {
        $ctx = $this->selling->for($user);
        $perPage = min((int) request('per_page', 25), 100);

        $shopIds = RepSourcedShop::query()
            ->where('rep_id', $ctx['rep_id'])
            ->pluck('retailer_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $zoneIds = $user->repProfile?->zoneIds() ?? [];

        return QueryBuilder::for(RetailerProfile::class)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where('shop_name', 'like', '%'.$value.'%');
                }),
            )
            ->where(function ($query) use ($shopIds, $zoneIds): void {
                if ($shopIds !== []) {
                    $query->whereIn('id', $shopIds);
                } else {
                    $query->whereRaw('0 = 1');
                }
                if ($zoneIds !== []) {
                    $query->orWhere(function ($q) use ($zoneIds): void {
                        $q->where('status', ProfileStatus::Active)
                            ->whereIn('zone_id', $zoneIds);
                    });
                }
            })
            ->with('user')
            ->defaultSort('-created_at')
            ->paginate($perPage);
    }
}
