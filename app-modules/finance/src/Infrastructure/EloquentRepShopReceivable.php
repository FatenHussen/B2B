<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepShopReceivable;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;

final class EloquentRepShopReceivable implements RepShopReceivable
{
    public function __construct(private readonly RepDirectory $reps) {}

    public function openTotal(int $repUserId, int $retailerId): int
    {
        $channelId = $this->reps->channelIdForUser($repUserId);
        if ($channelId === null) {
            return 0;
        }

        return (int) Tenant::as($channelId, fn () => Invoice::query()
            ->where('rep_id', $repUserId)
            ->where('retailer_id', $retailerId)
            ->where('status', 'open')
            ->get()
            ->sum(fn (Invoice $invoice) => max(0, $invoice->remaining())));
    }
}
