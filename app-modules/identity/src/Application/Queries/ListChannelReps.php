<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Core\Support\Tenant;
use Modules\Identity\Application\Support\ChannelRepPresenter;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListChannelReps
{
    public function __construct(private readonly ChannelRepPresenter $presenter) {}

    /**
     * @return LengthAwarePaginator<int, RepProfile>
     */
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 25), 100);
        $channelId = (int) Tenant::currentId();

        return QueryBuilder::for(RepProfile::query()->where('channel_id', $channelId)->with('user'))
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-id')
            ->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function map(RepProfile $row): array
    {
        $user = $row->user;
        if (! $user instanceof AppUser) {
            return [
                'id' => 0,
                'name' => null,
                'phone' => null,
                'status' => $row->status->value,
                'zone_ids' => [],
                'on_duty' => false,
                'max_discount_percent' => 0,
                'max_cash_hold' => 0,
            ];
        }

        return $this->presenter->present($row, $user);
    }
}
