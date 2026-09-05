<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read access to the audit trail. The write side is {@see RecordsAudit}.
 *
 * Core owns `audit_logs`. Access presents it — a listing screen, a CSV export and the
 * recent-usage strip on a permission — and none of those are a reason to hand out the
 * Eloquent model. The three methods below are the whole read surface; a fourth shape of
 * question belongs here as a fourth named method, not as a query builder returned to the
 * caller.
 *
 * `at` is the raw ISO-8601 timestamp as stored. Callers that display it apply their own
 * timezone, because the export deliberately does not.
 *
 * @phpstan-type AuditFilters array{
 *     actor?: int|string|null,
 *     action?: string|null,
 *     action_like?: string|null,
 *     action_any?: list<string>,
 *     channel_id?: int|string|null,
 *     date_from?: string|null,
 *     date_to?: string|null,
 * }
 * @phpstan-type AuditEntry array{
 *     at: string|null,
 *     actor: int|null,
 *     action: string|null,
 *     entity_type: string|null,
 *     entity_id: int|null,
 *     ip: string|null,
 *     properties: array<string, mixed>,
 * }
 */
interface AuditTrail
{
    /**
     * Newest first, paginated.
     *
     * @param  AuditFilters  $filters
     * @return LengthAwarePaginator<int, AuditEntry>
     */
    public function page(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Oldest first, streamed one row at a time so an export never loads the table.
     *
     * @param  AuditFilters  $filters
     * @return iterable<AuditEntry>
     */
    public function stream(array $filters): iterable;

    /**
     * Newest first, capped.
     *
     * @param  AuditFilters  $filters
     * @return list<AuditEntry>
     */
    public function recent(array $filters, int $limit): array;
}
