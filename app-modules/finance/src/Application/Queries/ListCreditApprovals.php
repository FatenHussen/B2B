<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Finance\Domain\Models\CreditApprovalRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListCreditApprovals
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 25), 100);

        return QueryBuilder::for(CreditApprovalRequest::class)
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-created_at')
            ->paginate($perPage);
    }

    /**
     * @return array{id: int, retailer_id: int, amount_minor: int, outstanding: int, credit_limit: int, status: string, created_at: string|null}
     */
    public function map(CreditApprovalRequest $row): array
    {
        return [
            'id' => (int) $row->id,
            'retailer_id' => (int) $row->retailer_id,
            'amount_minor' => (int) $row->amount_minor,
            'outstanding' => (int) $row->outstanding_at_request,
            'credit_limit' => (int) $row->credit_limit_at_request,
            'status' => (string) $row->status,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
