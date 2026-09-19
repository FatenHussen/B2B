<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepZoneRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListChannelRepZoneRequests
{
    /**
     * @return LengthAwarePaginator<int, RepZoneRequest>
     */
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 25), 100);
        $channelId = (int) Tenant::currentId();
        $profileIds = RepProfile::query()->where('channel_id', $channelId)->pluck('id');

        return QueryBuilder::for(
            RepZoneRequest::query()->whereIn('rep_id', $profileIds)
        )
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-id')
            ->paginate($perPage);
    }

    /**
     * @return array{id: int, rep_user_id: int|null, zone_id: int, note: string|null, status: string}
     */
    public function map(RepZoneRequest $row): array
    {
        $profile = RepProfile::query()->whereKey($row->rep_id)->first();

        return [
            'id' => (int) $row->id,
            'rep_user_id' => $profile !== null ? (int) $profile->app_user_id : null,
            'zone_id' => (int) $row->zone_id,
            'note' => $row->note,
            'status' => $row->status->value,
        ];
    }
}
