<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Access\Application\Services\PageSize;
use Modules\Access\Domain\Models\AccessChangeRequest;
use Modules\Core\Http\ApiResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListApprovalRequests
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = QueryBuilder::for(AccessChangeRequest::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
            )
            ->defaultSort('-created_at');

        $paginator = $query->paginate(PageSize::perPage($request));

        return ApiResponse::paginate($paginator, function (AccessChangeRequest $row): array {
            return [
                'id' => (int) $row->id,
                'permission' => $row->permission,
                'action' => $row->action,
                'requested_by' => (int) $row->requester_id,
                'payload' => $row->payload,
                'status' => $row->status->value,
                'created_at' => $row->created_at?->timezone('Asia/Damascus')->toIso8601String(),
            ];
        });
    }
}
