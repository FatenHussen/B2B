<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Core\Support\Tenant;
use Modules\Ordering\Domain\Models\SubOrder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListChannelSubOrders
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 25), 100);
        $waiting = $request->input('filter.waiting_over_minutes');

        $builder = QueryBuilder::for(SubOrder::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('zone_id'),
                AllowedFilter::exact('retailer_id'),
                AllowedFilter::exact('rep_id'),
                AllowedFilter::exact('source'),
            )
            ->where('channel_id', Tenant::currentId())
            ->defaultSort('-created_at');

        if ($waiting !== null && $waiting !== '') {
            $builder->where('created_at', '<=', now()->subMinutes((int) $waiting));
        }

        return $builder->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function map(SubOrder $row): array
    {
        return [
            'id' => (int) $row->id,
            'sub_order_no' => $row->sub_order_no,
            'status' => $row->status->value,
            'zone_id' => $row->zone_id ? (int) $row->zone_id : null,
            'total' => (int) $row->total,
        ];
    }
}
