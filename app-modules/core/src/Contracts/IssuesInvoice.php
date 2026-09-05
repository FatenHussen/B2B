<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface IssuesInvoice
{
    /**
     * First caller wins; later callers return the same header (idempotent).
     *
     * @return array{id: int, no: string, total: int}
     */
    public function issue(int $subOrderId, int $channelId, int $retailerId, int $totalMinor, ?int $repId = null): array;

    /**
     * @return array{id: int, no: string, total: int}|null
     */
    public function forSubOrder(int $subOrderId): ?array;

    public function replaceTotal(int $invoiceId, int $totalMinor): void;
}
