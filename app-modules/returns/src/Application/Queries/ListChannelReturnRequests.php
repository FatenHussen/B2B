<?php

declare(strict_types=1);

namespace Modules\Returns\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Returns\Domain\Models\ReturnRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListChannelReturnRequests
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 25), 100);

        return QueryBuilder::for(ReturnRequest::class)
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('rep_id'),
                AllowedFilter::exact('zone_id'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage);
    }

    /**
     * @return array{id: int, request_no: string, type: string, status: string}
     */
    public function map(ReturnRequest $row): array
    {
        return [
            'id' => (int) $row->id,
            'request_no' => (string) $row->request_no,
            'type' => (string) $row->type,
            'status' => (string) $row->status,
        ];
    }
}
