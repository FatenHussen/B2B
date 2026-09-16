<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Finance\Domain\Models\Invoice;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListInvoices
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 25), 100);

        return QueryBuilder::for(Invoice::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('retailer_id'),
                AllowedFilter::exact('rep_id'),
                AllowedFilter::callback('date', function ($query, $value): void {
                    $query->whereDate('created_at', (string) $value);
                }),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage);
    }

    /**
     * @return array{id: int, no: string, total: int, status: string}
     */
    public function map(Invoice $row): array
    {
        return [
            'id' => (int) $row->id,
            'no' => (string) $row->no,
            'total' => (int) $row->total,
            'status' => (string) $row->status,
        ];
    }
}
