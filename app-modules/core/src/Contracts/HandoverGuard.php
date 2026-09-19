<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface HandoverGuard
{
    public function isConfirmedForSubOrder(int $subOrderId): bool;

    /**
     * Open warehouse receipts waiting on this rep (handover items, not handover rows).
     * A number for EP-RP-002 — Fulfillment owns `handovers`; Identity must not read them.
     */
    public function pendingReceiptCount(int $repUserId): int;
}
