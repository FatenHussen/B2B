<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Modules\Core\Contracts\RetailerCreditSnapshot;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\RetailerCreditLimit;

final class EloquentRetailerCreditSnapshot implements RetailerCreditSnapshot
{
    public function forRetailer(int $retailerId, int $channelId): array
    {
        $row = Tenant::as($channelId, fn () => RetailerCreditLimit::query()
            ->where('retailer_id', $retailerId)
            ->first());

        if ($row === null) {
            return ['credit_limit' => null, 'grace_days' => null, 'on_exceed' => null];
        }

        return [
            'credit_limit' => (int) $row->credit_limit,
            'grace_days' => (int) $row->grace_days,
            'on_exceed' => (string) $row->on_exceed,
        ];
    }

    public function outstanding(int $retailerId, int $channelId): int
    {
        return (int) Tenant::as($channelId, fn () => Invoice::query()
            ->where('retailer_id', $retailerId)
            ->where('status', 'open')
            ->get()
            ->sum(fn (Invoice $invoice) => max(0, $invoice->remaining())));
    }
}
