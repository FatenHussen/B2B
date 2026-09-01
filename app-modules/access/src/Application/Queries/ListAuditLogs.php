<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Access\Application\Services\PageSize;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Core\Http\ApiResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListAuditLogs
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = QueryBuilder::for(AuditLog::class)
            ->allowedFilters(
                AllowedFilter::exact('actor', 'actor_id'),
                AllowedFilter::exact('action'),
                AllowedFilter::exact('channel_id'),
                AllowedFilter::callback('date_from', function ($q, $value): void {
                    $q->whereDate('created_at', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($q, $value): void {
                    $q->whereDate('created_at', '<=', $value);
                }),
            )
            ->defaultSort('-created_at');

        $paginator = $query->paginate(PageSize::perPage($request));

        return ApiResponse::paginate($paginator, function (AuditLog $log): array {
            $properties = is_array($log->properties) ? $log->properties : [];

            return [
                'at' => $log->created_at?->timezone('Asia/Damascus')->toIso8601String(),
                'actor' => $log->actor_id,
                'action' => $log->action,
                'entity_type' => $log->subject_type,
                'entity_id' => $log->subject_id,
                'before' => $properties['before'] ?? null,
                'after' => $properties['after'] ?? null,
                'ip' => $log->ip,
                'impersonated' => (bool) ($properties['impersonated'] ?? false),
            ];
        });
    }
}
