<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Domain\Models\StockTransfer;
use Modules\Inventory\Domain\Models\StockTransferLine;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListStockTransfers
{
    /**
     * @return LengthAwarePaginator<int, StockTransfer>
     */
    public function __invoke(): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 25), 100);

        return QueryBuilder::for(StockTransfer::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::callback('warehouse_id', function (Builder $query, mixed $value): void {
                    $id = (int) $value;
                    $query->where(function (Builder $q) use ($id): void {
                        $q->where('from_warehouse_id', $id)->orWhere('to_warehouse_id', $id);
                    });
                }),
            )
            ->defaultSort('-id')
            ->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function map(StockTransfer $row): array
    {
        return [
            'id' => (int) $row->id,
            'from_warehouse_id' => (int) $row->from_warehouse_id,
            'to_warehouse_id' => (int) $row->to_warehouse_id,
            'status' => $row->status->value,
            'lines_count' => StockTransferLine::query()->where('stock_transfer_id', $row->id)->count(),
            'created_at' => $row->created_at?->timezone('Asia/Damascus')->toIso8601String(),
        ];
    }
}
