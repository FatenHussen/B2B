<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Access\Application\Services\PageSize;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Http\ApiResponse;

final class ListAuditLogs
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $filter */
        $filter = $request->input('filter', []);

        $paginator = $this->audit->page([
            'actor' => $filter['actor'] ?? null,
            'action' => $filter['action'] ?? null,
            'channel_id' => $filter['channel_id'] ?? null,
            'date_from' => $filter['date_from'] ?? null,
            'date_to' => $filter['date_to'] ?? null,
        ], PageSize::perPage($request));

        return ApiResponse::paginate($paginator, function (array $entry): array {
            $properties = $entry['properties'];

            return [
                'at' => $entry['at'] === null
                    ? null
                    : CarbonImmutable::parse($entry['at'])->timezone('Asia/Damascus')->toIso8601String(),
                'actor' => $entry['actor'],
                'action' => $entry['action'],
                'entity_type' => $entry['entity_type'],
                'entity_id' => $entry['entity_id'],
                'before' => $properties['before'] ?? null,
                'after' => $properties['after'] ?? null,
                'ip' => $entry['ip'],
                'impersonated' => (bool) ($properties['impersonated'] ?? false),
            ];
        });
    }
}
